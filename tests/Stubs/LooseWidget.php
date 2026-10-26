<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MatrixPlatform\Attributes\Unreferenced;

class LooseWidget extends Widget {

    /**
     * @return HasOne<Trinket, $this>
     */
    #[Unreferenced]
    public function sole(): HasOne {
        return $this->hasOne(Trinket::class, 'widget_id');
    }

    /**
     * @return HasMany<Trinket, $this>
     */
    #[Unreferenced]
    public function trinkets(): HasMany {
        return $this->hasMany(Trinket::class, 'widget_id');
    }

}
