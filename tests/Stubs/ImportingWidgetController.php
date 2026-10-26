<?php //>

namespace Tests\Stubs;

class ImportingWidgetController extends WidgetController {

    protected ?array $imports = [
        '*title'
    ];

}
