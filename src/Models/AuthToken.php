<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorUserAgent;

class AuthToken extends BaseModel {

    const TRACEABLE = false;

    protected $casts = [
        'expire_time' => 'datetime'
    ];

    protected $generators = [
        'ip' => CreatorAddress::class,
        'user_agent' => CreatorUserAgent::class
    ];

    protected $table = 'base_auth_token';

    public function freshTimestamp() {
        return now()->startOfMinute();
    }

    public static function findByToken($token, $type) {
        return $token ? self::where(['token' => $token, 'type' => $type])->whereNotExpired()->first() : null;
    }

}
