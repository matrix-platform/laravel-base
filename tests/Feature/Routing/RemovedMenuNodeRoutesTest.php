<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Menus;
use Tests\FeatureTestCase;
use Tests\Stubs\WidgetController;

class RemovedMenuNodeRoutesTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $this->useMenuFixtures('removal staged');

        $router->prefix('admin')->group(function () use ($router): void {
            $router->middleware(['envelope-api', 'user-api', 'permission-api'])->group(fn () => ActionRoutes::mount('widget', WidgetController::class));
        });
    }

    public function test_a_node_overridden_with_null_is_not_registered(): void {
        $uris = array_map(fn ($route): string => $route->uri(), Route::getRoutes()->getRoutes());

        $this->assertNotContains('admin/widget/{id}/update', $uris);
        $this->assertContains('admin/widget/insert', $uris);
        $this->assertFalse(app(Menus::class)->has('widget/{id}/update'));
    }

    public function test_a_node_overridden_with_null_answers_endpoint_not_found(): void {
        $this->postJson('admin/widget/1/update')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

}
