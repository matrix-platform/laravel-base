<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\SmsLog;
use Mockery;
use Tests\FeatureTestCase;
use Tests\Stubs\Gizmo;
use Tests\Stubs\OkDriver;

class DispatchMessagesCommandTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->useMessagingFixtures();
    }

    private function dispatch(): PendingCommand {
        return $this->artisanCommand('messages:dispatch');
    }

    private function mail(MessageStatus $status = MessageStatus::Scheduled, ?string $at = null, string $provider = 'stub'): MailLog {
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

    private function sms(): SmsLog {
        $log = new SmsLog();

        $log->provider = 'twilio';
        $log->receiver = '0912345678';
        $log->content = 'Your code is 123456';
        $log->schedule_time = now()->subMinute();
        $log->status = MessageStatus::Scheduled;
        $log->locale = 'en';

        $log->save();

        return $log;
    }

    public function test_a_due_message_dispatches_a_job_for_its_channel(): void {
        $this->mail();

        $this->dispatch()->assertSuccessful();

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail');
    }

    public function test_a_channel_gets_a_single_job_however_many_messages_are_waiting(): void {
        $this->mail();
        $this->mail();
        $this->mail(MessageStatus::Scheduled, null, 'relay');

        $this->dispatch()->assertSuccessful();

        Queue::assertPushed(SendMessageJob::class, 1);
    }

    public function test_a_channel_with_nothing_waiting_dispatches_nothing(): void {
        $this->dispatch()->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_message_scheduled_for_the_future_dispatches_nothing(): void {
        $this->mail(MessageStatus::Scheduled, '+1 day');

        $this->dispatch()->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_message_that_already_reached_a_terminal_state_dispatches_nothing(): void {
        $this->mail(MessageStatus::Success);
        $this->mail(MessageStatus::Failed);

        $this->dispatch()->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_job_is_held_until_the_writing_transaction_commits(): void {
        $this->mail();

        $this->dispatch()->assertSuccessful();

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->afterCommit === true);
    }

    public function test_a_job_lands_on_the_queue_its_channel_declares(): void {
        config()->set('matrix.messaging.channels.mail.queue', 'somewhere-else');

        $this->mail();

        $this->dispatch()->assertSuccessful();

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->queue === 'somewhere-else');
    }

    public function test_the_shipped_channels_are_served_by_a_queue_each(): void {
        $this->mail();
        $this->sms();

        $this->dispatch()->assertSuccessful();

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail' && $job->queue === 'messaging-mail');
        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'sms' && $job->queue === 'messaging-sms');
    }

    public function test_a_broken_channel_fails_the_command_and_the_others_still_go_out(): void {
        config()->set('matrix.messaging.channels.pigeon', ['model' => Gizmo::class, 'queue' => 'pigeon-post', 'driver' => OkDriver::class]);

        $this->mail();

        $this->dispatch()
            ->expectsOutputToContain('pigeon: invalid-message-channel')
            ->assertFailed();

        Queue::assertPushed(SendMessageJob::class, 1);
    }

    public function test_a_broken_channel_is_written_to_the_application_log(): void {
        $spy = Log::spy();

        config()->set('matrix.messaging.channels.pigeon', ['model' => Gizmo::class, 'queue' => 'pigeon-post', 'driver' => OkDriver::class]);

        $this->dispatch()->assertFailed();

        $spy->shouldHaveReceived('error', ['messaging.pigeon.misconfigured', Mockery::on(fn (array $context) => array_get_value($context, 'channel') === 'pigeon'
            && array_get_value($context, 'code') === 'invalid-message-channel')]);
    }

}
