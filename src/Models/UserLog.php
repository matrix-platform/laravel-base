<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;

class UserLog extends BaseModel {

    const CREATED_AT = 'create_time';

    protected $generators = ['ip' => CreatorAddress::class];
    protected $table = 'base_user_log';

}
