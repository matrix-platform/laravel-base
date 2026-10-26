<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;

class SmokeTest extends FeatureTestCase {

    public function test_the_database_driver_is_postgres(): void {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_the_metadata_registry_is_a_singleton(): void {
        $this->assertSame(app(MetadataRegistry::class), app(MetadataRegistry::class));
    }

}
