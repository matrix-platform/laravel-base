<?php //>

namespace Tests\Feature\Messaging;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Mail\MessageMail;
use MatrixPlatform\Messaging\MailerMailDriver;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Support\Resources;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
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

    private function sendIgnoringTheRefusedConnection(): void {
        try {
            (new MailerMailDriver())->send($this->log());
        } catch (TransportExceptionInterface) {
        }
    }

    public function test_a_message_goes_to_the_real_recipient(): void {
        (new MailerMailDriver())->send($this->log());

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('alice@example.com') && $mail->subjectLine === 'Hello');
    }

    public function test_the_recorded_cc_and_bcc_are_delivered(): void {
        $log = $this->log();

        $log->cc = 'bob@example.com, carol@example.com';
        $log->bcc = 'dave@example.com';

        (new MailerMailDriver())->send($log);

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('alice@example.com')
            && $mail->hasCc('bob@example.com')
            && $mail->hasCc('carol@example.com')
            && $mail->hasBcc('dave@example.com'));
    }

    public function test_a_sandbox_run_does_not_copy_anyone_else(): void {
        $this->useMessagingFixtures();

        $log = $this->log();

        $log->provider = 'sandboxed';
        $log->cc = 'bob@example.com';
        $log->bcc = 'dave@example.com';

        (new MailerMailDriver())->send($log);

        Mail::assertSent(MessageMail::class, fn (MessageMail $mail) => $mail->hasTo('sink@example.com')
            && !$mail->hasCc('bob@example.com')
            && !$mail->hasBcc('dave@example.com'));
    }

    public function test_the_connection_comes_from_the_provider_the_record_names(): void {
        $this->useMessagingFixtures();

        $log = $this->log();

        $log->provider = 'relay';

        (new MailerMailDriver())->send($log);

        $this->assertSame('smtp.relay.example', config('mail.mailers.matrix-smtp:relay.host'));
        $this->assertSame(2525, config('mail.mailers.matrix-smtp:relay.port'));
        $this->assertSame('smtps', config('mail.mailers.matrix-smtp:relay.scheme'));
    }

    public function test_a_second_provider_on_the_same_channel_is_reachable(): void {
        $this->useMessagingFixtures();

        (new MailerMailDriver())->send($this->log());

        $this->assertSame(cfg('gmail.host'), config('mail.mailers.matrix-smtp:gmail.host'));
        $this->assertSame('smtp', config('mail.mailers.matrix-smtp:gmail.transport'));
    }

    public function test_each_provider_gets_its_own_mailer_so_a_resolved_one_cannot_be_reused(): void {
        $this->useMessagingFixtures();

        $relay = $this->log();

        $relay->provider = 'relay';

        (new MailerMailDriver())->send($this->log());
        (new MailerMailDriver())->send($relay);

        $this->assertSame(cfg('gmail.host'), config('mail.mailers.matrix-smtp:gmail.host'));
        $this->assertSame('smtp.relay.example', config('mail.mailers.matrix-smtp:relay.host'));
        $this->assertNull(config('mail.mailers.matrix-smtp'));
    }

    public function test_a_changed_connection_rebuilds_the_transport_of_an_already_resolved_mailer(): void {
        Mail::swap(new MailManager(app()));

        $this->useCfg('gmail', ['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'tls']);
        $this->sendIgnoringTheRefusedConnection();

        $override = ResourceOverride::query()->where('bundle', 'cfg/gmail')->firstOrFail();
        $override->data = ['host' => '127.0.0.1', 'port' => 2, 'encryption' => 'tls'];
        $override->save();

        app(Resources::class)->forget();
        $this->sendIgnoringTheRefusedConnection();

        $transport = Mail::mailer('matrix-smtp:gmail')->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transport);

        $stream = $transport->getStream();

        $this->assertInstanceOf(SocketStream::class, $stream);
        $this->assertSame(2, $stream->getPort());
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
