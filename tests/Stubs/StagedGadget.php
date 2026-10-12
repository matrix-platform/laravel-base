<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $widget_id
 */
class StagedGadget extends DriveGadget {

    /**
     * @return BelongsTo<Widget, $this>
     */
    public function widget(): BelongsTo {
        return $this->belongsTo(Widget::class);
    }

}
