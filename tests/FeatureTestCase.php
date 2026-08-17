<?php //>

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use MatrixPlatform\Exceptions\ServiceException;
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

    protected function refuses(string $slug, callable $callback): void {
        try {
            $callback();
        } catch (ServiceException $exception) {
            $this->assertSame($slug, $exception->getError());

            return;
        }

        $this->fail("expected the call to be refused with '{$slug}'");
    }

    protected function useCfgFixtures(): void {
        $this->usePackageFixtures('cfg-fixture', 'package-format');
    }

    protected function useMenuFixtures(string $menus): void {
        $this->usePackageFixtures('menu-fixture', 'package-menu');

        $this->useMenus($menus);
    }

    protected function useMenus(?string $menus): void {
        config()->set('matrix.admin-menus', $menus);

        app()->forgetInstance(Menus::class);
    }

    protected function useMessagingFixtures(): void {
        $this->usePackageFixtures('messaging-fixture', 'package-messaging');
    }

    private function usePackageFixtures(string $package, string $directory): void {
        app(PackageRegistry::class)->register($package, __DIR__ . "/fixtures/{$directory}");

        config()->set('matrix.packages', "{$package} app base");

        app()->forgetInstance(Resources::class);
    }

}
