<?php //>

namespace Tests\Stubs;

class ListingWidgetController extends WidgetController {

    public static int $listings = 0;

    protected ?array $exports = null;

    protected function listing(): array {
        self::$listings++;

        return [...parent::listing(), ['name' => 'ip', 'op' => 'eq']];
    }

}
