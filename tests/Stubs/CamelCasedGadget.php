<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MatrixPlatform\Models\BaseModel;

/**
 * @property int $id
 * @property ?string $title
 */
class CamelCasedGadget extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'stub_gadget';

    /**
     * @return HasMany<Trinket, $this>
     */
    public function camelCasedTrinkets(): HasMany {
        return $this->hasMany(Trinket::class, 'gadget_id');
    }

}
