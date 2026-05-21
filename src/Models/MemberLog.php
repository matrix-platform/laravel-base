<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

class MemberLog extends BaseModel {

    const UPDATED_AT = null;
    const UPDATED_BY = null;

    const TRACEABLE = false;

    protected $casts = [
        'content' => 'array'
    ];

    protected $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_member_log';

}
