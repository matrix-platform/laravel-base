<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property string $label
 * @property ?int $widget_id
 * @property ?int $trinket_id
 * @property ?int $gadget_id
 * @property ?int $amount
 * @property ?int $owner_id
 * @property ?string $owner_type
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class Trinket extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_trinket';

    /**
     * @return BelongsTo<Gadget, $this>
     */
    public function gadget(): BelongsTo {
        return $this->belongsTo(Gadget::class);
    }

    /**
     * @return BelongsTo<Trinket, $this>
     */
    public function trinket(): BelongsTo {
        return $this->belongsTo(Trinket::class);
    }

    /**
     * @return HasMany<Trinket, $this>
     */
    public function trinkets(): HasMany {
        return $this->hasMany(Trinket::class);
    }

    /**
     * @return BelongsTo<Widget, $this>
     */
    public function widget(): BelongsTo {
        return $this->belongsTo(Widget::class);
    }

}
