<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $bundle
 * @property array<string, mixed> $data
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class ResourceOverride extends BaseModel {

    protected $table = 'base_resource_override';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'data' => 'array'
        ];
    }

}
