<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorEndpoint;

class ManipulationLog extends BaseModel {

    const UPDATED_AT = null;
    const UPDATED_BY = null;

    const TRACEABLE = false;

    protected $casts = [
        'before' => 'array',
        'after' => 'array'
    ];

    protected $generators = [
        'endpoint' => CreatorEndpoint::class,
        'ip' => CreatorAddress::class
    ];

    protected $table = 'base_manipulation_log';

}
