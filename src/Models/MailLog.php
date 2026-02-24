<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;

class MailLog extends BaseModel {

    const CREATED_AT = 'create_time';

    protected $casts = ['send_time' => 'datetime'];
    protected $generators = ['ip' => CreatorAddress::class];
    protected $table = 'base_mail_log';

}
