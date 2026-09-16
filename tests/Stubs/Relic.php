<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property string $label
 * @property ?int $relic_id
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 * @property ?Carbon $deleted_at
 */
class Relic extends BaseModel {

    use SoftDeletes;

    const TRACEABLE = false;

    protected $table = 'stub_relic';

    /**
     * @return BelongsTo<Relic, $this>
     */
    public function relic(): BelongsTo {
        return $this->belongsTo(Relic::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'deleted_at' => 'datetime'
        ];
    }

}
