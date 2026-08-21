<?php //>

namespace Tests\Feature\Mail;

use MatrixPlatform\Mail\MessageMail;
use Tests\FeatureTestCase;

class MessageMailTest extends FeatureTestCase {

    public function test_the_mail_carries_the_subject_the_body_and_the_sender(): void {
        $mail = new MessageMail('Hello', '<p>Body</p>', 'noreply@example.com', 'Matrix');

        $html = $mail->render();

        $this->assertSame('Hello', $mail->subject);
        $this->assertStringContainsString('<p>Body</p>', $html);
        $this->assertTrue($mail->hasFrom('noreply@example.com', 'Matrix'));
    }

    public function test_the_mail_leaves_the_sender_to_the_mailer_when_the_provider_declares_none(): void {
        $mail = new MessageMail('Hello', '<p>Body</p>');

        $mail->render();

        $this->assertSame([], $mail->from);
    }

}
