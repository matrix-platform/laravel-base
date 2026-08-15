<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Attributes\Action;

class MiddleController extends StubController {

    #[Action(path: 'middle-path')]
    public function named(): string {
        return 'middle';
    }

}
