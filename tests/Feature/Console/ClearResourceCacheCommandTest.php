<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;
use Tests\FeatureTestCase;

class ClearResourceCacheCommandTest extends FeatureTestCase {

    private function command(): void {
        $this->artisanCommand('matrix:clear-resource-cache')->assertExitCode(0);
    }

    private function fixture(string $data): string {
        $path = sys_get_temp_dir() . '/matrix-clear-cache-' . bin2hex(random_bytes(4));

        File::ensureDirectoryExists("{$path}/resources/cfg");
        File::put("{$path}/resources/cfg/temp.php", $data);

        app(PackageRegistry::class)->register('clear-cache-fixture', $path);
        config()->set('matrix.packages', 'clear-cache-fixture app base');
        config()->set('matrix.resource-cache-enabled', true);
        config()->set('matrix.resource-cache-store', 'array');

        return $path;
    }

    private function fresh(): Resources {
        app()->forgetScopedInstances();

        return app(Resources::class);
    }

    public function test_a_stale_file_default_is_re_read_after_clearing(): void {
        $path = $this->fixture("<?php return ['key' => 'old'];");

        $this->assertSame('old', app(Resources::class)->config('temp.key'));

        File::put("{$path}/resources/cfg/temp.php", "<?php return ['key' => 'new'];");

        $this->assertSame('old', $this->fresh()->config('temp.key'));

        $this->command();

        $this->assertSame('new', $this->fresh()->config('temp.key'));

        File::deleteDirectory($path);
    }

    public function test_a_stale_override_written_outside_resource_service_is_re_read_after_clearing(): void {
        $path = $this->fixture("<?php return ['key' => 'default'];");

        $this->assertSame('default', app(Resources::class)->config('temp.key'));

        $override = new ResourceOverride();

        $override->bundle = 'cfg/temp';
        $override->data = ['key' => 'overridden'];

        $override->save();

        $this->assertSame('default', $this->fresh()->config('temp.key'));

        $this->command();

        $this->assertSame('overridden', $this->fresh()->config('temp.key'));

        File::deleteDirectory($path);
    }

    public function test_it_succeeds_when_nothing_has_been_cached_yet(): void {
        $this->command();
    }

}
