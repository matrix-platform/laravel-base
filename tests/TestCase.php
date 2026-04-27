<?php //>

namespace Tests;

use MatrixPlatform\BaseServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase {

    protected function getPackageProviders($app) {
        return [BaseServiceProvider::class];
    }

}
