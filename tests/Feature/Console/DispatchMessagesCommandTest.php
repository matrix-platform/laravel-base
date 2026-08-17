<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\SmsLog;
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
        $command = $this->artisan('messages:dispatch');

        if (!$command instanceof PendingCommand) {
            $this->fail('artisan() did not return a pending command');
        }

        return $command;
    }

    private function mail(MessageStatus $status, ?string $at = null, string $provider = 'stub'): MailLog {
        $log = new MailLog();

        $log->provider = $provider;
        $log->sender = 'noreply@example.com';
        $log->receiver = 'alice@example.com';
        $log->subject = 'Hello';
        $log->content = 'Body';
        $log->schedule_time = $at === null ? now()->subMinute() : now()->modify($at);
        $log->status = $status;

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

        $log->save();

        return $log;
    }

    public function test_a_due_scheduled_message_is_claimed_and_queued(): void {
        $log = $this->mail(MessageStatus::Scheduled);

        $this->dispatch()->assertSuccessful();

        $this->assertSame(MessageStatus::Sending, $log->refresh()->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail' && $job->id === $log->id);
    }

    public function test_a_message_scheduled_for_the_future_is_left_alone(): void {
        $log = $this->mail(MessageStatus::Scheduled, '+1 day');

        $this->dispatch()->assertSuccessful();

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_message_in_any_other_state_is_left_alone(): void {
        $sending = $this->mail(MessageStatus::Sending);
        $failed = $this->mail(MessageStatus::Failed);

        $this->dispatch()->assertSuccessful();

        $this->assertSame(MessageStatus::Sending, $sending->refresh()->status);
        $this->assertSame(MessageStatus::Failed, $failed->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_channel_without_a_driver_is_skipped_rather_than_failed(): void {
        $log = $this->sms();

        $this->dispatch()->assertSuccessful();

        $this->assertSame(MessageStatus::Scheduled, $log->refresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_a_broken_channel_is_reported_and_the_others_still_go_out(): void {
        config()->set('matrix.messaging.channels.pigeon', ['model' => Gizmo::class, 'driver' => OkDriver::class]);

        $log = $this->mail(MessageStatus::Scheduled);

        $this->dispatch()
            ->expectsOutputToContain('pigeon: invalid-message-channel')
            ->assertSuccessful();

        $this->assertSame(MessageStatus::Sending, $log->refresh()->status);

        Queue::assertPushed(SendMessageJob::class);
    }

}
