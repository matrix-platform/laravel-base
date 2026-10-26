<?php //>

namespace Tests\Stubs;

class MovableTrinketController extends TrinketController {

    protected bool $movable = true;

    protected array $updates = [
        '*label',
        'amount',
        'widget_id'
    ];

}
