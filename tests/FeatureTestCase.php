<?php //>

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    }

}
