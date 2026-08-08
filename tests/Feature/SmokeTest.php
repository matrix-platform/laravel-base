<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\BaseServiceProvider;
use Tests\FeatureTestCase;

class SmokeTest extends FeatureTestCase {

    public function test_database_connection_is_postgres(): void {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_database_is_reachable(): void {
        $this->assertSame(1, DB::selectOne('select 1 as ok')->ok);
    }

    public function test_test_database_is_the_configured_one(): void {
        $this->assertSame(DB::connection()->getDatabaseName(), DB::selectOne('select current_database() as name')->name);
    }

    public function test_service_provider_is_registered(): void {
        $this->assertNotNull($this->app?->getProvider(BaseServiceProvider::class));
    }

}
