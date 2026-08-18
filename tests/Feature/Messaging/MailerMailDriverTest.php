<?php //>

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\Mail;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Mail\MessageMail;
use MatrixPlatform\Messaging\MailerMailDriver;
use MatrixPlatform\Models\MailLog;
use Tests\FeatureTestCase;

class MailerMailDriverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Mail::fake();
    }

    private function log(): MailLog {
        $log = new MailLog();

        $log->provider = 'gmail';
        $log->receiver = 'alice@example.com';
        $log->subject = 'Hello';
        $log->content = '<p>Body</p>';

        return $log;
    }

    public function test_a_message_goes_to_the_real_recipient(): void {
        (new MailerMailDriver())->send($this->log());

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('alice@example.com') && $mail->subjectLine === 'Hello');
    }

    public function test_the_connection_comes_from_the_provider_the_record_names(): void {
        $this->useMessagingFixtures();

        $log = $this->log();

        $log->provider = 'relay';

        (new MailerMailDriver())->send($log);

        $this->assertSame('smtp.relay.example', config('mail.mailers.matrix-smtp.host'));
        $this->assertSame(2525, config('mail.mailers.matrix-smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.matrix-smtp.scheme'));
    }

    public function test_a_second_provider_on_the_same_channel_is_reachable(): void {
        $this->useMessagingFixtures();

        (new MailerMailDriver())->send($this->log());

        $this->assertSame(cfg('gmail.host'), config('mail.mailers.matrix-smtp.host'));
        $this->assertSame('smtp', config('mail.mailers.matrix-smtp.transport'));
    }

    public function test_the_body_is_delivered_as_html_without_escaping(): void {
        (new MailerMailDriver())->send($this->log());

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->body === '<p>Body</p>');
    }

    public function test_a_sandbox_run_redirects_the_message_and_keeps_the_original_recipient_in_the_subject(): void {
        $this->useMessagingFixtures();

        $log = $this->log();

        $log->provider = 'sandboxed';

        $response = (new MailerMailDriver())->send($log);

        $this->assertStringStartsWith('sandbox:', $response);
        $this->assertStringContainsString('sink@example.com', $response);

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('sink@example.com')
            && !$mail->hasTo('alice@example.com')
            && $mail->subjectLine === 'Hello [alice@example.com]');
    }

    public function test_a_sandbox_run_without_a_recipient_is_refused(): void {
        $this->useMessagingFixtures();

        $log = $this->log();

        $log->provider = 'blind';

        $this->expectException(ServiceException::class);

        (new MailerMailDriver())->send($log);
    }

}
