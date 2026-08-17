<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\SmsLogDeclaration;

#[Declared(SmsLogDeclaration::class)]
class SmsLog extends MessageLog {

    protected $table = 'base_sms_log';

}
