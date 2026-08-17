<?php //>

namespace Tests\Feature\Services\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Services\Messaging\MailService;
use MatrixPlatform\Services\Messaging\SmsService;
use Mockery;
use Tests\FeatureTestCase;

class MessageServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->useMessagingFixtures();
    }

    private function mail(): MailService {
        return app(MailService::class);
    }

    private function reload(MessageLog $log): MailLog {
        return MailLog::query()
            ->whereKey($log->id)
            ->firstOrFail();
    }

    private function sms(): SmsService {
        return app(SmsService::class);
    }

    public function test_sending_stores_the_rendered_message_and_queues_it(): void {
        $log = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);
        $mail = $this->reload($log);

        $this->assertSame('alice@example.com', $mail->receiver);
        $this->assertSame('Welcome Alice', $mail->subject);
        $this->assertSame('Dear Alice, welcome aboard.', $mail->content);
        $this->assertSame('welcome', $mail->template);
        $this->assertSame(MessageStatus::Sending, $log->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail' && $job->id === $log->id);
    }

    public function test_scheduling_for_the_future_stores_the_message_without_queueing_it(): void {
        $log = $this->mail()->schedule(now()->addDay(), 'alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->assertSame(MessageStatus::Scheduled, $log->status);
        $this->assertTrue($log->schedule_time->isFuture());

        Queue::assertNothingPushed();
    }

    public function test_a_channel_without_a_driver_still_records_the_message(): void {
        $log = $this->sms()->send('0912345678', 'otp', ['code' => '123456']);

        $this->assertSame(MessageStatus::Scheduled, $log->status);
        $this->assertSame('Your code is 123456', $log->content);
        $this->assertSame('otp', $log->template);
        $this->assertSame(1, SmsLog::query()->count());

        Queue::assertNothingPushed();
    }

    public function test_an_empty_receiver_is_refused(): void {
        $this->refuses('invalid-message-receiver', fn () => $this->mail()->send('', 'welcome', ['name' => 'Alice']));
        $this->refuses('invalid-message-receiver', fn () => $this->mail()->schedule(now(), '', 'welcome', ['name' => 'Alice']));
    }

    public function test_a_message_without_any_content_is_refused(): void {
        $this->refuses('invalid-message-content', fn () => $this->mail()->send('alice@example.com'));
    }

    public function test_an_unknown_template_is_refused(): void {
        $this->refuses('message-template-not-found', fn () => $this->mail()->send('alice@example.com', 'does-not-exist'));
    }

    public function test_options_override_the_rendered_template(): void {
        $log = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice'], ['subject' => 'Overridden', 'content' => 'Replaced']);

        $this->assertSame('Overridden', $this->reload($log)->subject);
        $this->assertSame('Replaced', $log->content);
    }

    public function test_an_option_that_is_explicitly_null_does_not_override(): void {
        $log = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice'], ['subject' => null]);

        $this->assertSame('Welcome Alice', $this->reload($log)->subject);
    }

    public function test_the_template_that_produced_the_message_is_recorded_on_every_channel(): void {
        $mail = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);
        $sms = $this->sms()->send('0912345678', 'otp', ['code' => '123456']);

        $this->assertSame('welcome', $this->reload($mail)->template);
        $this->assertSame('otp', SmsLog::query()->whereKey($sms->id)->firstOrFail()->template);
    }

    public function test_a_message_without_a_template_is_carried_entirely_by_the_options(): void {
        $log = $this->mail()->send('alice@example.com', null, [], ['provider' => 'stub', 'subject' => 'Bare', 'content' => 'Body only']);
        $mail = $this->reload($log);

        $this->assertSame('Bare', $mail->subject);
        $this->assertSame('Body only', $mail->content);
        $this->assertNull($mail->template);
    }

    public function test_the_sender_is_snapshotted_from_the_provider_that_will_carry_the_message(): void {
        $mail = $this->reload($this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']));

        $this->assertSame('stub', $mail->provider);
        $this->assertSame('stub@example.com', $mail->sender);
    }

    public function test_a_template_routes_the_message_to_the_provider_it_names(): void {
        $sms = $this->sms()->send('0912345678', 'otp', ['code' => '123456']);

        $this->assertSame('twilio', $sms->provider);
    }

    public function test_the_caller_can_override_the_provider_the_template_names(): void {
        $log = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice'], ['provider' => 'relay']);

        $this->assertSame('relay', $log->provider);
        $this->assertSame('relay@example.com', $this->reload($log)->sender);
    }

    public function test_a_message_with_no_template_is_routed_by_the_caller(): void {
        $log = $this->mail()->send('alice@example.com', null, [], ['provider' => 'stub', 'subject' => 'Bare', 'content' => 'Body']);

        $this->assertSame('stub', $log->provider);
    }

    public function test_a_message_that_names_no_provider_at_all_is_refused(): void {
        $this->refuses('invalid-message-provider', fn () => $this->mail()->send('alice@example.com', null, [], ['content' => 'Bare']));
    }

    public function test_a_resend_can_be_routed_to_a_different_provider(): void {
        $original = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);
        $copy = $this->mail()->resend($original->id, 'relay');

        $this->assertSame('stub', $original->provider);
        $this->assertSame('relay', $copy->provider);
        $this->assertSame('alice@example.com', $copy->receiver);
    }

    public function test_resending_copies_the_record_and_leaves_the_original_alone(): void {
        $original = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);

        $original->status = MessageStatus::Failed;
        $original->error = 'smtp exploded';
        $original->response = 'nope';
        $original->send_time = now();

        $original->save();

        Queue::fake();

        $copy = $this->mail()->resend($original->id);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertNull($copy->send_time);
        $this->assertNull($copy->response);
        $this->assertNull($copy->error);
        $this->assertSame('alice@example.com', $copy->receiver);
        $this->assertSame(MessageStatus::Sending, $copy->status);
        $this->assertSame(MessageStatus::Failed, $original->fresh()?->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->id === $copy->id);
    }

    public function test_cancelling_a_scheduled_message_marks_it_failed(): void {
        $log = $this->mail()->schedule(now()->addDay(), 'alice@example.com', 'welcome', ['name' => 'Alice']);

        $cancelled = $this->mail()->cancel($log->id);

        $this->assertSame(MessageStatus::Failed, $cancelled->status);
        $this->assertSame('cancelled', $cancelled->error);
    }

    public function test_cancelling_a_message_that_already_went_out_changes_nothing(): void {
        $log = $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);

        $log->status = MessageStatus::Success;

        $log->save();

        $cancelled = $this->mail()->cancel($log->id);

        $this->assertSame(MessageStatus::Success, $cancelled->status);
        $this->assertNull($cancelled->error);
    }

    public function test_every_action_is_written_to_the_application_log(): void {
        $spy = Log::spy();

        $this->mail()->send('alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->assertSame(1, MailLog::query()->count());

        $spy->shouldHaveReceived('info', ['messaging.mail.send', Mockery::on(fn (array $context) => array_get_value($context, 'to') === 'alice@example.com'
            && array_get_value($context, 'action') === 'send'
            && array_get_value($context, 'channel') === 'mail')]);
    }

}
