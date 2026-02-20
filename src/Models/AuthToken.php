<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

class AuthToken extends BaseModel {

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'modify_time';

    protected $casts = ['expire_time' => 'datetime'];
    protected $generators = ['ip' => CreatorAddress::class, 'user_agent' => CreatorUserAgent::class];
    protected $table = 'base_auth_token';

    public static function findByToken($token, $type) {
        return $token ? self::where('token', hash('sha256', $token))->where('type', $type)->whereNotExpired()->first() : null;
    }

}
