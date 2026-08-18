<?php //>

namespace MatrixPlatform\Messaging;

use Illuminate\Support\Facades\Mail;
use MatrixPlatform\Mail\MessageMail;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;

/**
 * @implements Driver<MailLog>
 */
class MailerMailDriver implements Driver {

    private const MAILER = 'matrix-smtp';

    public function send(MessageLog $log): string {
        $bundle = $log->provider;
        $sandbox = Sandbox::recipient($bundle);
        $to = $sandbox === null ? $log->receiver : $sandbox;
        $subject = $sandbox === null ? $log->subject : "{$log->subject} [{$log->receiver}]";

        config()->set('mail.mailers.' . self::MAILER, $this->mailer($bundle));

        $sent = Mail::mailer(self::MAILER)
            ->to($to)
            ->send(new MessageMail($subject, $log->content, strval(cfg("{$bundle}.from-address")), strval(cfg("{$bundle}.from-name"))));

        $id = $sent === null ? '' : strval($sent->getMessageId());

        return Sandbox::response($sandbox, $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function mailer(string $bundle): array {
        return [
            'transport' => 'smtp',
            'scheme' => cfg("{$bundle}.encryption") === 'ssl' ? 'smtps' : 'smtp',
            'host' => cfg("{$bundle}.host"),
            'port' => (int) cfg("{$bundle}.port"),
            'username' => cfg("{$bundle}.username"),
            'password' => cfg("{$bundle}.password")
        ];
    }

}
