<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorEndpoint;

class ManipulationLog extends BaseModel {

    const TRACEABLE = false;
    const UPDATED_AT = null;
    const UPDATED_BY = null;

    protected array $generators = [
        'endpoint' => CreatorEndpoint::class,
        'ip' => CreatorAddress::class
    ];

    protected $table = 'base_manipulation_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'after' => 'array',
            'before' => 'array',
            'type' => ManipulationType::class
        ];
    }

}
