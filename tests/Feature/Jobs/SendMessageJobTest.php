<?php //>

namespace Tests\Feature\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use Tests\FeatureTestCase;
use Tests\Stubs\OkDriver;

class SendMessageJobTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        OkDriver::$level = null;

        $this->useMessagingFixtures();
    }

    /**
     * @return list<int>
     */
    private function delays(QueueFake $queue): array {
        $pushed = array_get_value($queue->pushedJobs(), SendMessageJob::class, []);

        return array_map(fn (array $entry) => intval($entry['job']->delay), is_array($pushed) ? array_values($pushed) : []);
    }

    private function throttle(string $provider, int $times): void {
        $resolved = app(Channels::class)->get('mail')->provider($provider);

        foreach (range(1, $times) as $id) {
            SendMessageJob::dispatchThrottled($resolved, $id);
        }
    }

    private function execute(MailLog $log): MailLog {
        (new SendMessageJob('mail', $log->id))->handle();

        return $log->refresh();
    }

    private function stored(MessageStatus $status = MessageStatus::Sending, string $provider = 'stub'): MailLog {
        $log = new MailLog();

        $log->provider = $provider;
        $log->sender = 'noreply@example.com';
        $log->receiver = 'alice@example.com';
        $log->subject = 'Hello';
        $log->content = 'Body';
        $log->schedule_time = now();
        $log->status = $status;

        $log->save();

        return $log;
    }

    public function test_a_successful_send_records_the_response_and_the_time(): void {
        $log = $this->execute($this->stored());

        $this->assertSame(MessageStatus::Success, $log->status);
        $this->assertSame('stub-message-id', $log->response);
        $this->assertNotNull($log->send_time);
        $this->assertNull($log->error);
    }

    public function test_a_driver_that_blows_up_leaves_the_record_failed_rather_than_sending(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'fail'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('smtp exploded', $log->error);
    }

    public function test_a_refusal_records_the_error_slug_rather_than_the_message(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'refusing'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('invalid-message-receiver', $log->error);
    }

    public function test_a_record_that_is_not_sending_is_left_untouched(): void {
        $log = $this->execute($this->stored(MessageStatus::Success));

        $this->assertSame(MessageStatus::Success, $log->status);
        $this->assertNull($log->response);
        $this->assertNull(OkDriver::$level);
    }

    public function test_a_diagnosis_the_driver_wrote_is_not_overwritten_by_the_generic_slug(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'diagnosing'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('vendor-status-6', $log->error);
    }

    public function test_a_provider_with_no_driver_fails_the_record_instead_of_stranding_it(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'bare'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('message-provider-has-no-driver', $log->error);
    }

    public function test_a_provider_naming_a_class_that_is_not_a_driver_fails_the_record(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'broken'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('invalid-message-driver', $log->error);
    }

    public function test_a_provider_whose_configuration_vanished_fails_the_record(): void {
        $log = $this->execute($this->stored(MessageStatus::Sending, 'evaporated'));

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('invalid-message-provider', $log->error);
    }

    public function test_the_driver_runs_outside_the_transaction_that_claimed_the_record(): void {
        $baseline = DB::transactionLevel();

        $this->execute($this->stored());

        $this->assertSame($baseline, OkDriver::$level);
    }

    public function test_the_job_is_held_until_the_writing_transaction_commits(): void {
        Queue::fake();

        $this->throttle('stub', 1);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->afterCommit === true);
    }

    public function test_the_job_lands_on_the_configured_queue(): void {
        Queue::fake();

        config()->set('matrix.messaging.queue', 'messaging');

        $this->throttle('stub', 1);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->queue === 'messaging');
    }

    public function test_a_zero_interval_means_no_delay(): void {
        $queue = Queue::fake();

        $this->throttle('instant', 2);

        $this->assertSame([0, 0], $this->delays($queue));
    }

    public function test_consecutive_dispatches_are_spaced_by_the_configured_interval(): void {
        $queue = Queue::fake();

        $this->throttle('throttled', 3);

        $this->assertSame([0, 60, 120], $this->delays($queue));
    }

    public function test_two_providers_on_one_channel_are_throttled_independently(): void {
        $queue = Queue::fake();

        $this->throttle('throttled', 2);
        $this->throttle('paced', 2);

        $this->assertSame([0, 60, 0, 30], $this->delays($queue));
    }

    public function test_a_pause_longer_than_the_old_cache_lifetime_does_not_reset_the_spacing(): void {
        $queue = Queue::fake();

        $this->throttle('throttled', 5);

        $this->assertSame([0, 60, 120, 180, 240], $this->delays($queue));

        $this->travel(241)->seconds();

        $this->throttle('throttled', 1);

        $spacing = $this->delays($queue);

        $this->assertCount(6, $spacing);
        $this->assertGreaterThan(0, end($spacing));
    }

}
