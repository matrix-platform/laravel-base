<?php //>

namespace Tests;

use Illuminate\Foundation\Application;
use MatrixPlatform\BaseServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase {

    /**
     * @param Application $app
     * @return class-string[]
     */
    protected function getPackageProviders($app): array {
        return [BaseServiceProvider::class];
    }

}
