<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\MenuDeclaration;

/**
 * @property int $id
 * @property ?int $parent_id
 * @property ?string $title__tw
 * @property ?string $title__en
 * @property ?array<string, mixed> $data
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(MenuDeclaration::class)]
class Menu extends BaseModel {

    protected $table = 'base_menu';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'data' => 'array',
            'disable_time' => 'datetime',
            'enable_time' => 'datetime'
        ];
    }

}
