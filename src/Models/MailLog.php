<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;

class MailLog extends BaseModel {

    const TRACEABLE = false;

    protected $casts = [
        'send_time' => 'datetime'
    ];

    protected $generators = [
        'ip' => CreatorAddress::class
    ];

    protected $table = 'base_mail_log';

}
