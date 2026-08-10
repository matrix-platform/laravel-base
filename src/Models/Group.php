<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\GroupDeclaration;

/**
 * @property int $id
 * @property string $title
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(GroupDeclaration::class)]
class Group extends BaseModel {

    protected $table = 'base_group';

}
