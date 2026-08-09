<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

class SmokeTest extends FeatureTestCase {

    public function test_the_database_driver_is_postgres(): void {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_the_package_registers_its_middleware_aliases(): void {
        $aliases = array_keys(Route::getMiddleware());

        $this->assertContains('envelope-api', $aliases);
        $this->assertContains('locale-api', $aliases);
        $this->assertContains('user-api', $aliases);
    }

}
