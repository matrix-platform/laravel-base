<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

class MemberLog extends BaseModel {

    const CREATED_AT = 'create_time';

    protected $casts = ['content' => 'json'];
    protected $generators = ['ip' => CreatorAddress::class, 'user_agent' => CreatorUserAgent::class];
    protected $table = 'base_member_log';

}
