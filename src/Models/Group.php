<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Casts\PermissionMap;
use MatrixPlatform\Models\Declarations\GroupDeclaration;

/**
 * @property int $id
 * @property string $title
 * @property-read array<string, array<string, bool>> $permissions
 * @property-write array<string, mixed>|object|null $permissions
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(GroupDeclaration::class)]
class Group extends BaseModel {

    protected $attributes = [
        'permissions' => '{}'
    ];

    protected $table = 'base_group';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'permissions' => PermissionMap::class
        ];
    }

}
