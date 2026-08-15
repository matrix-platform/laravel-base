<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Attributes\Action;

class LeafController extends MiddleController {

    public function named(): string {
        return 'leaf';
    }

    public function plain(): string {
        return 'leaf';
    }

    #[Action]
    protected function guarded(): string {
        return 'guarded';
    }

}
