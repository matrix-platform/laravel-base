<?php //>

namespace Tests\Feature\Support;

use Illuminate\Routing\Router;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\SharedModels;
use Tests\FeatureTestCase;
use Tests\Stubs\Trinket;
use Tests\Stubs\TrinketController;
use Tests\Stubs\Widget;
use Tests\Stubs\WidgetController;

class SharedModelsTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(function (): void {
                ActionRoutes::mount('widget/{widget_id}/trinket', TrinketController::class);
                ActionRoutes::mount('widget', WidgetController::class);
            });
    }

    public function test_a_parent_mounted_after_its_child_still_resolves_to_its_own_model(): void {
        $this->assertSame(Widget::class, app(SharedModels::class)->resolve('widget'));
        $this->assertSame(Trinket::class, app(SharedModels::class)->resolve('widget/{widget_id}/trinket'));
    }

    public function test_the_user_prefix_is_not_taken_by_a_resource_mounted_under_it(): void {
        $this->assertSame(User::class, app(SharedModels::class)->resolve('user'));
    }

    public function test_a_route_pattern_that_is_not_a_mount_prefix_resolves_to_nothing(): void {
        $this->assertNull(app(SharedModels::class)->resolve('user/{id}'));
        $this->assertNull(app(SharedModels::class)->resolve('widget/{id}'));
    }

}
