<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Attributes\Action;

class ExtendedWidgetController extends WidgetController {

    /**
     * @return array{class: class-string}
     */
    #[Action]
    public function ping(): array {
        return ['class' => static::class];
    }

}
