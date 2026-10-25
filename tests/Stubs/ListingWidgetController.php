<?php //>

namespace Tests\Stubs;

class ListingWidgetController extends WidgetController {

    protected function listing(): array {
        return [...parent::listing(), ['name' => 'ip', 'op' => 'eq']];
    }

}
