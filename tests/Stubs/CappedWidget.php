<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CappedWidget extends LooseWidget {

    /**
     * @return HasMany<Trinket, $this>
     */
    public function capped(): HasMany {
        return $this->hasMany(Trinket::class, 'widget_id')->limit(1);
    }

}
