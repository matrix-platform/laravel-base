<?php //>

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeatureTestCase extends TestCase {

    use RefreshDatabase;

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void {
        $app['config']->set('app.locale', 'tw');
    }

}
