<?php //>

namespace Tests\Feature\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use Mockery;
use Tests\FeatureTestCase;
use Tests\Stubs\FailDriver;
use Tests\Stubs\OkDriver;

class SendMessageJobTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        Sleep::fake();

        OkDriver::$level = null;

        $this->useMessagingFixtures();
    }

    private function execute(): void {
        (new SendMessageJob('mail'))->handle();
    }

    private function stored(MessageStatus $status = MessageStatus::Scheduled, string $provider = 'stub', ?string $at = null): MailLog {
        $log = new MailLog();

        $log->provider = $provider;
        $log->sender = 'noreply@example.com';
        $log->receiver = 'alice@example.com';
        $log->subject = 'Hello';
        $log->content = 'Body';
        $log->schedule_time = $at === null ? now()->subMinute() : now()->modify($at);
        $log->status = $status;
        $log->locale = 'en';

        $log->save();

        return $log;
    }

    public function test_the_message_that_is_due_is_sent_and_recorded(): void {
        $log = $this->stored();

        $this->execute();

        $log->refresh();

        $this->assertSame(MessageStatus::Success, $log->status);
        $this->assertSame('stub-message-id', $log->response);
        $this->assertNotNull($log->send_time);
        $this->assertNull($log->error);
    }

    public function test_the_earliest_schedule_time_goes_first_whatever_the_write_order(): void {
        $late = $this->stored(MessageStatus::Scheduled, 'stub', '-1 minute');
        $early = $this->stored(MessageStatus::Scheduled, 'stub', '-5 minutes');

        $this->execute();

        $this->assertSame(MessageStatus::Success, $early->refresh()->status);
        $this->assertSame(MessageStatus::Scheduled, $late->refresh()->status);
    }

    public function test_only_one_message_goes_out_per_job(): void {
        $first = $this->stored();
        $second = $this->stored();

        $this->execute();

        $this->assertSame(MessageStatus::Success, $first->refresh()->status);
        $this->assertSame(MessageStatus::Scheduled, $second->refresh()->status);
    }

    public function test_a_send_hands_the_work_to_a_successor(): void {
        $this->stored();

        $this->execute();

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail');
    }

    public function test_the_successor_that_finds_nothing_left_ends_the_chain(): void {
        $this->stored();

        $this->execute();
        $this->execute();

        Queue::assertPushed(SendMessageJob::class, 1);
    }

    public function test_a_job_with_nothing_to_do_ends_without_sending_or_handing_off(): void {
        $this->execute();

        $this->assertNull(OkDriver::$level);

        Queue::assertNothingPushed();
    }

    public function test_a_message_scheduled_for_the_future_is_left_alone(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'stub', '+1 day');

        $this->execute();

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);
        $this->assertNull(OkDriver::$level);
    }

    public function test_a_message_that_already_reached_a_terminal_state_is_left_alone(): void {
        $log = $this->stored(MessageStatus::Success);

        $this->execute();

        $this->assertSame(MessageStatus::Success, $log->refresh()->status);
        $this->assertNull($log->response);
        $this->assertNull(OkDriver::$level);
    }

    public function test_a_driver_that_blows_up_leaves_the_record_failed(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'fail');

        $this->execute();

        $log->refresh();

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('smtp exploded', $log->error);
    }

    public function test_a_refusal_records_the_error_slug_rather_than_the_message(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'refusing');

        $this->execute();

        $log->refresh();

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('invalid-message-receiver', $log->error);
    }

    public function test_a_diagnosis_the_driver_wrote_is_not_overwritten_by_the_generic_slug(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'diagnosing');

        $this->execute();

        $log->refresh();

        $this->assertSame(MessageStatus::Failed, $log->status);
        $this->assertSame('vendor-status-6', $log->error);
    }

    public function test_a_failed_send_is_written_to_the_application_log(): void {
        $spy = Log::spy();

        $log = $this->stored(MessageStatus::Scheduled, 'fail');

        $this->execute();

        $spy->shouldHaveReceived('error', ['messaging.mail.failed', Mockery::on(fn (array $context) => array_get_value($context, 'channel') === 'mail'
            && array_get_value($context, 'provider') === 'fail'
            && array_get_value($context, 'id') === $log->id
            && array_get_value($context, 'code') === 'smtp exploded')]);
    }

    public function test_a_provider_with_no_driver_fails_the_job_and_leaves_its_messages_waiting(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'bare');

        $this->refuses('message-provider-has-no-driver', fn () => $this->execute());

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_provider_naming_a_class_that_is_not_a_driver_fails_the_job_and_leaves_its_messages_waiting(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'broken');

        $this->refuses('invalid-message-driver', fn () => $this->execute());

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_provider_whose_configuration_vanished_fails_the_job_and_leaves_its_messages_waiting(): void {
        $log = $this->stored(MessageStatus::Scheduled, 'evaporated');

        $this->refuses('invalid-message-provider', fn () => $this->execute());

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_message_at_the_head_that_cannot_be_delivered_blocks_the_ones_behind_it(): void {
        $bare = $this->stored(MessageStatus::Scheduled, 'bare');
        $stub = $this->stored();

        $this->refuses('message-provider-has-no-driver', fn () => $this->execute());

        $this->assertSame(MessageStatus::Scheduled, $bare->refresh()->status);
        $this->assertSame(MessageStatus::Scheduled, $stub->refresh()->status);
    }

    public function test_the_configured_interval_is_slept_off_before_handing_over(): void {
        $this->stored(MessageStatus::Scheduled, 'throttled');

        $this->execute();

        Sleep::assertSequence([Sleep::for(60)->seconds()]);
    }

    public function test_a_provider_without_an_interval_does_not_sleep(): void {
        $this->stored();

        $this->execute();

        Sleep::assertSleptTimes(0);
    }

    public function test_a_failed_send_is_paced_like_a_successful_one(): void {
        $this->useCfg('throttled', ['driver' => FailDriver::class]);

        $log = $this->stored(MessageStatus::Scheduled, 'throttled');

        $this->execute();

        $this->assertSame(MessageStatus::Failed, $log->refresh()->status);

        Sleep::assertSequence([Sleep::for(60)->seconds()]);
    }

    public function test_two_providers_on_one_channel_are_paced_by_whichever_one_just_sent(): void {
        $this->stored(MessageStatus::Scheduled, 'throttled', '-5 minutes');
        $this->stored(MessageStatus::Scheduled, 'paced', '-1 minute');

        $this->execute();
        $this->execute();

        Sleep::assertSequence([Sleep::for(60)->seconds(), Sleep::for(30)->seconds()]);
    }

    public function test_the_driver_runs_outside_any_transaction_the_job_opened(): void {
        $baseline = DB::transactionLevel();

        $this->stored();

        $this->execute();

        $this->assertSame($baseline, OkDriver::$level);
    }

}
