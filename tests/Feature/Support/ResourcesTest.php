<?php //>

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\File;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;
use Tests\FeatureTestCase;

class ResourcesTest extends FeatureTestCase {

    private function fixtures(string $order = 'a b'): Resources {
        $packages = new PackageRegistry();

        $packages->register('a', __DIR__ . '/../../fixtures/package-a');
        $packages->register('b', __DIR__ . '/../../fixtures/package-b');

        config()->set('matrix.packages', $order);

        return new Resources($packages);
    }

    public function test_first_package_in_the_config_list_wins(): void {
        $resources = $this->fixtures();

        $this->assertSame('from-a', $resources->config('demo.shared'));
    }

    public function test_reversing_the_config_list_reverses_priority(): void {
        $resources = $this->fixtures('b a');

        $this->assertSame('from-b', $resources->config('demo.shared'));
        $this->assertSame(['b-one', 'b-two', 'b-three'], $resources->config('demo.list'));
    }

    public function test_package_missing_from_the_config_list_is_ignored(): void {
        $resources = $this->fixtures('a');

        $this->assertSame('from-a', $resources->config('demo.shared'));
        $this->assertNull($resources->config('demo.only-in-b'));
    }

    public function test_keys_missing_from_the_first_package_come_from_the_second(): void {
        $resources = $this->fixtures();

        $this->assertSame('B', $resources->config('demo.only-in-b'));
        $this->assertSame('A', $resources->config('demo.only-in-a'));
    }

    public function test_bundle_present_in_only_one_package_still_loads(): void {
        $resources = $this->fixtures();

        $this->assertSame('value', $resources->config('only-in-b.key'));
    }

    public function test_list_values_are_replaced_whole(): void {
        $resources = $this->fixtures();

        $this->assertSame(['a-only'], $resources->config('demo.list'));
    }

    public function test_map_values_merge_recursively(): void {
        $resources = $this->fixtures();

        $this->assertSame(['icon' => 'a-icon', 'severity' => 'b-severity'], $resources->config('demo.map'));
    }

    public function test_values_of_different_shapes_are_replaced_whole(): void {
        $resources = $this->fixtures();

        $this->assertSame(['k' => 'v'], $resources->config('demo.shape'));
    }

    public function test_config_returns_default_for_unknown_key_and_bundle(): void {
        $resources = $this->fixtures();

        $this->assertSame('D', $resources->config('demo.nope', 'D'));
        $this->assertSame('D', $resources->config('no-such-bundle.nope', 'D'));
        $this->assertNull($resources->config('demo.nope'));
    }

    public function test_dotted_key_is_taken_literally(): void {
        $resources = $this->fixtures();

        $this->assertSame('A-DEEP', $resources->translate('demo.nested.deep.key'));
    }

    public function test_translate_reads_the_current_locale(): void {
        $resources = $this->fixtures();

        $this->assertSame('YES-FROM-A', $resources->translate('demo.common.yes'));
        $this->assertSame('NO-FROM-B', $resources->translate('demo.common.no'));
    }

    public function test_translate_returns_the_token_when_missing(): void {
        $resources = $this->fixtures();

        $this->assertSame('demo.nope', $resources->translate('demo.nope'));
        $this->assertSame('no-such-bundle.nope', $resources->translate('no-such-bundle.nope'));
    }

    public function test_token_without_a_dot_is_rejected(): void {
        $resources = $this->fixtures();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-resource-token');

        $resources->config('nodot');
    }

    public function test_bundle_survives_the_file_being_deleted_after_the_first_read(): void {
        $path = $this->temporary();
        $file = "{$path}/resources/cfg/temp.php";

        File::put($file, "<?php return ['key' => 'cached'];");

        $resources = $this->packaged($path);

        $this->assertSame('cached', $resources->config('temp.key'));

        File::delete($file);
        clearstatcache();

        $this->assertSame('cached', $resources->config('temp.key'));

        File::deleteDirectory($path);
    }

    public function test_missing_bundle_is_cached_as_missing(): void {
        $path = $this->temporary();
        $resources = $this->packaged($path);

        $this->assertNull($resources->config('ghost.key'));

        File::put("{$path}/resources/cfg/ghost.php", "<?php return ['key' => 'appeared'];");
        clearstatcache();

        $this->assertNull($resources->config('ghost.key'));

        File::deleteDirectory($path);
    }

    private function packaged(string $path): Resources {
        $packages = new PackageRegistry();

        $packages->register('t', $path);

        config()->set('matrix.packages', 't');

        return new Resources($packages);
    }

    private function temporary(): string {
        $path = sys_get_temp_dir() . '/matrix-resources-' . bin2hex(random_bytes(4));

        File::ensureDirectoryExists("{$path}/resources/cfg");

        return $path;
    }

}
