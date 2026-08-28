<?php //>

namespace Tests\Stubs;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Support\RollbackCallbacks;
use RuntimeException;

class StubController extends BaseController {

    #[Action]
    public function boom(): never {
        error('data-conflicted', 409);
    }

    #[Action]
    public function boomWithFields(): never {
        error('invalid-password', 422, ['fields' => ['current' => ['invalid-password']]]);
    }

    #[Action(middleware: 'throttle:1,1')]
    public function limited(): string {
        return 'limited';
    }

    #[Action]
    public function missing(): never {
        Widget::query()->findOrFail(0);

        error('data-conflicted');
    }

    #[Action(path: 'custom-path')]
    public function named(): string {
        return 'named';
    }

    #[Action('')]
    public function nameless(): string {
        return 'nameless';
    }

    #[Action(scope: 'anonymous')]
    public function open(): string {
        return 'open';
    }

    #[Action]
    public function pingPong(): string {
        return 'ping-pong';
    }

    #[Action]
    public function plain(): string {
        return 'plain';
    }

    #[Action]
    public function raw(): mixed {
        return response()->json(['raw' => true]);
    }

    #[Action]
    public function rollback(): never {
        app(RollbackCallbacks::class)->register(fn () => cache()->forever('rollback-ran', true));

        Widget::forceCreate(['title' => 'doomed']);

        error('data-conflicted', 409);
    }

    #[Action]
    public function unexpected(): never {
        throw new RuntimeException('internal detail that must not leak');
    }

    #[Action]
    public function validated(Request $request): string {
        $request->validate(['name' => ['required'], 'age' => ['required', 'integer']]);

        return 'ok';
    }

    public function notAnAction(): string {
        return 'hidden';
    }

}
