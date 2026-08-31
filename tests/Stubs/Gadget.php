<?php //>

namespace Tests\Stubs;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property ?string $title
 * @property ?string $translated__tw
 * @property ?string $translated__en
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Gadget extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_gadget';

}
