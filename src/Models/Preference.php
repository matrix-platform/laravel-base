<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property IdentityType $identity_type
 * @property int $identity_id
 * @property array<string, mixed> $data
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Preference extends BaseModel {

    protected $attributes = [
        'data' => '{}'
    ];

    protected $table = 'base_preference';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'data' => 'array',
            'identity_type' => IdentityType::class
        ];
    }

}
