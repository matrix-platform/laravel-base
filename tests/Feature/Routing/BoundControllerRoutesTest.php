<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\FeatureTestCase;
use Tests\Stubs\ExtendedWidgetController;
use Tests\Stubs\GadgetController;
use Tests\Stubs\WidgetController;

class BoundControllerRoutesTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        app()->bind(WidgetController::class, ExtendedWidgetController::class);

        $router->prefix('admin')->group(function () use ($router): void {
            $router->middleware('envelope-api')->group(function (): void {
                ActionRoutes::mount('widget', WidgetController::class);
                ActionRoutes::mount('gadget', GadgetController::class);
            });
        });
    }

    private function controllerOf(string $uri): ?string {
        $route = Route::getRoutes()->match(request()->create($uri, 'POST'));
        $controller = $route->getAction('controller');

        return is_string($controller) ? Str::before($controller, '@') : null;
    }

    public function test_an_action_added_by_the_bound_subclass_is_registered(): void {
        $this->postJson('admin/widget/ping')->assertJsonPath('data.class', ExtendedWidgetController::class);
    }

    public function test_inherited_actions_route_to_the_bound_subclass(): void {
        $this->assertSame(ExtendedWidgetController::class, $this->controllerOf('admin/widget/insert'));
    }

    public function test_an_unbound_controller_keeps_its_own_class(): void {
        $this->assertSame(GadgetController::class, $this->controllerOf('admin/gadget/insert'));
    }

}
