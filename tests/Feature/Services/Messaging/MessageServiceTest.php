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

    public function test_a_message_that_is_already_due_is_stored_and_queued(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']);
        $mail = $this->reload($log);

        $this->assertSame('alice@example.com', $mail->receiver);
        $this->assertSame('Welcome Alice', $mail->subject);
        $this->assertSame('Dear Alice, welcome aboard.', $mail->content);
        $this->assertSame('welcome', $mail->template);
        $this->assertSame(MessageStatus::Scheduled, $log->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail');
    }

    public function test_scheduling_for_the_future_stores_the_message_without_queueing_it(): void {
        $log = $this->mail()->schedule(now()->addDay(), 'alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->assertSame(MessageStatus::Scheduled, $log->status);
        $this->assertTrue($log->schedule_time->isFuture());

        Queue::assertNothingPushed();
    }

    public function test_an_empty_receiver_is_refused(): void {
        $this->refuses('invalid-message-receiver', fn () => $this->mail()->schedule(now(), '', 'welcome', ['name' => 'Alice']));
    }

    public function test_a_message_without_any_content_is_refused(): void {
        $this->refuses('invalid-message-content', fn () => $this->mail()->schedule(now(), 'alice@example.com'));
    }

    public function test_an_unknown_template_is_refused(): void {
        $this->refuses('message-template-not-found', fn () => $this->mail()->schedule(now(), 'alice@example.com', 'does-not-exist'));
    }

    public function test_options_override_the_rendered_template(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice'], ['subject' => 'Overridden', 'content' => 'Replaced']);

        $this->assertSame('Overridden', $this->reload($log)->subject);
        $this->assertSame('Replaced', $log->content);
    }

    public function test_an_option_that_is_explicitly_null_does_not_override(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice'], ['subject' => null]);

        $this->assertSame('Welcome Alice', $this->reload($log)->subject);
    }

    public function test_the_template_that_produced_the_message_is_recorded_on_every_channel(): void {
        $mail = $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']);
        $sms = $this->sms()->schedule(now(), '0912345678', 'otp', ['code' => '123456']);

        $this->assertSame('welcome', $this->reload($mail)->template);
        $this->assertSame('otp', SmsLog::query()->whereKey($sms->id)->firstOrFail()->template);
    }

    public function test_a_message_without_a_template_is_carried_entirely_by_the_options(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', null, [], ['provider' => 'stub', 'subject' => 'Bare', 'content' => 'Body only']);
        $mail = $this->reload($log);

        $this->assertSame('Bare', $mail->subject);
        $this->assertSame('Body only', $mail->content);
        $this->assertNull($mail->template);
    }

    public function test_the_sender_is_snapshotted_from_the_provider_that_will_carry_the_message(): void {
        $mail = $this->reload($this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']));

        $this->assertSame('stub', $mail->provider);
        $this->assertSame('stub@example.com', $mail->sender);
    }

    public function test_a_template_routes_the_message_to_the_provider_it_names(): void {
        $sms = $this->sms()->schedule(now(), '0912345678', 'otp', ['code' => '123456']);

        $this->assertSame('twilio', $sms->provider);
    }

    public function test_the_caller_can_override_the_provider_the_template_names(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice'], ['provider' => 'relay']);

        $this->assertSame('relay', $log->provider);
        $this->assertSame('relay@example.com', $this->reload($log)->sender);
    }

    public function test_a_message_with_no_template_is_routed_by_the_caller(): void {
        $log = $this->mail()->schedule(now(), 'alice@example.com', null, [], ['provider' => 'stub', 'subject' => 'Bare', 'content' => 'Body']);

        $this->assertSame('stub', $log->provider);
    }

    public function test_a_message_that_names_no_provider_at_all_is_refused(): void {
        $this->refuses('invalid-message-provider', fn () => $this->mail()->schedule(now(), 'alice@example.com', null, [], ['content' => 'Bare']));
    }

    public function test_a_provider_that_has_no_driver_is_refused(): void {
        $this->refuses('message-provider-has-no-driver', fn () => $this->mail()->schedule(now(), 'alice@example.com', null, [], ['provider' => 'bare', 'content' => 'Bare']));

        $this->assertSame(0, MailLog::query()->count());
    }

    public function test_every_call_is_written_to_the_application_log(): void {
        $spy = Log::spy();

        $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->assertSame(1, MailLog::query()->count());

        $spy->shouldHaveReceived('info', ['messaging.mail.schedule', Mockery::on(fn (array $context) => array_get_value($context, 'to') === 'alice@example.com'
            && array_get_value($context, 'action') === 'schedule'
            && array_get_value($context, 'channel') === 'mail')]);
    }

}
