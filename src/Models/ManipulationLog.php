<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\Generators\CreatorAddress;
use MatrixPlatform\Models\Generators\CreatorEndpoint;

/**
 * @property int $id
 * @property ManipulationType $type
 * @property ?string $endpoint
 * @property ?string $ip
 * @property string $data_type
 * @property int $data_id
 * @property-read ?array<string, mixed> $before
 * @property-write array<string, mixed>|object|null $before
 * @property-read ?array<string, mixed> $after
 * @property-write array<string, mixed>|object|null $after
 * @property ?int $creator_id
 * @property Carbon $create_time
 */
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
