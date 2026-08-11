<?php //>

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;

class FeatureTestCase extends TestCase {

    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void {
        $this->loadMigrationsFrom(__DIR__ . '/Stubs/migrations');
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void {
        $app['config']->set('app.locale', 'en');

        Event::listen(RequestHandled::class, fn () => $app->forgetScopedInstances());
    }

    protected function useMenuFixtures(string $menus): void {
        app(PackageRegistry::class)->register('menu-fixture', __DIR__ . '/fixtures/package-menu');

        config()->set('matrix.packages', 'menu-fixture app base');
        config()->set('matrix.admin-menus', $menus);

        app()->forgetInstance(Menus::class);
        app()->forgetInstance(Resources::class);
    }

}
