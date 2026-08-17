<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\MailLogDeclaration;

/**
 * @property string $sender
 * @property string $subject
 */
#[Declared(MailLogDeclaration::class)]
class MailLog extends MessageLog {

    protected $table = 'base_mail_log';

}
