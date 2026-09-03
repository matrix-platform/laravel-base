<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;

class SmokeTest extends FeatureTestCase {

    public function test_the_database_driver_is_postgres(): void {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_the_metadata_registry_is_a_singleton(): void {
        $this->assertSame(app(MetadataRegistry::class), app(MetadataRegistry::class));
    }

    public function test_the_registry_resolves_the_title_column_a_model_declares(): void {
        $this->assertSame('username', app(MetadataRegistry::class)->of(User::class)?->title);
    }

    public function test_the_package_registers_its_middleware_aliases(): void {
        $aliases = array_keys(Route::getMiddleware());

        $this->assertContains('envelope-api', $aliases);
        $this->assertContains('locale-api', $aliases);
        $this->assertContains('member-api', $aliases);
        $this->assertContains('member-aware-api', $aliases);
        $this->assertContains('permission-api', $aliases);
        $this->assertContains('user-api', $aliases);
        $this->assertContains('vendor-api', $aliases);
    }

}
