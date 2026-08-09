<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Group extends BaseModel {

    protected $table = 'base_group';

}
