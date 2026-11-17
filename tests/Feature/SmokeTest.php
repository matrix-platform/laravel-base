<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

class SmokeTest extends FeatureTestCase {

    public function test_the_database_driver_is_postgres(): void {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

}
