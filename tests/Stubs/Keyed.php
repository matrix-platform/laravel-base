<?php //>

namespace Tests\Stubs;

use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $code
 * @property string $title
 * @property int $ranking
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Keyed extends BaseModel {

    const TRACEABLE = false;

    protected $primaryKey = 'code';

    protected $table = 'stub_keyed';

}
