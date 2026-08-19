<?php //>

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ResourceOverride;
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

    private function overriding(string $bundle, mixed $data): void {
        $override = new ResourceOverride();

        $override->bundle = $bundle;
        $override->data = $data;

        $override->save();
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

    public function test_every_read_method_refuses_a_name_that_climbs_out_of_the_resource_directory(): void {
        $resources = $this->fixtures();

        $this->refuses('invalid-resource-token', fn () => $resources->getBundle('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->getConfigBundle('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->getI18nBundle('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->getMenuBundle('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->getStyleBundle('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->getDefaults('../config/app'));
        $this->refuses('invalid-resource-token', fn () => $resources->bundleNames('../config'));
    }

    public function test_a_token_can_never_carry_a_traversing_bundle_name(): void {
        $resources = $this->fixtures();

        $this->assertNull($resources->config('../../../config/app.key'));
        $this->assertSame('../../../config/app.key', $resources->translate('../../../config/app.key'));
    }

    public function test_a_traversing_name_never_reaches_the_file_system(): void {
        $resources = $this->fixtures();

        $this->refuses('invalid-resource-token', fn () => $resources->getBundle('../package-b/resources/cfg/only-in-b'));
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


    public function test_a_bundle_without_an_override_row_equals_its_defaults(): void {
        $resources = $this->fixtures();

        $this->assertSame($resources->getDefaults('cfg/demo'), $resources->getBundle('cfg/demo'));
    }

    public function test_an_override_replaces_only_the_keys_it_declares(): void {
        $this->overriding('cfg/demo', ['shared' => 'from-override']);

        $resources = $this->fixtures();

        $this->assertSame('from-override', $resources->config('demo.shared'));
        $this->assertSame('A', $resources->config('demo.only-in-a'));
        $this->assertSame('from-a', array_get_value($resources->getDefaults('cfg/demo'), 'shared'));
    }

    public function test_an_override_keeps_the_type_it_was_stored_with(): void {
        $this->overriding('cfg/demo', ['shared' => 600]);

        $this->assertSame(600, $this->fixtures()->config('demo.shared'));
    }

    public function test_an_override_of_a_nested_value_merges_key_by_key(): void {
        $this->overriding('cfg/demo', ['map' => ['icon' => 'overridden']]);

        $map = $this->fixtures()->config('demo.map');

        $this->assertSame(['icon' => 'overridden', 'severity' => 'b-severity'], $map);
    }

    public function test_an_override_cannot_conjure_a_bundle_that_has_no_defaults(): void {
        $this->overriding('cfg/phantom', ['key' => 'value']);

        $resources = $this->fixtures();

        $this->assertNull($resources->getBundle('cfg/phantom'));
        $this->assertNull($resources->config('phantom.key'));
        $this->assertSame(['key' => 'value'], $resources->getOverrides('cfg/phantom'));
    }

    public function test_a_row_whose_data_is_not_an_object_is_treated_as_absent(): void {
        $this->overriding('cfg/demo', 'not-an-object');

        $this->assertSame('from-a', $this->fixtures()->config('demo.shared'));
    }

    public function test_every_override_arrives_in_a_single_query(): void {
        $this->overriding('cfg/demo', ['shared' => 'x']);
        $this->overriding('cfg/only-in-b', ['key' => 'y']);

        $resources = $this->fixtures();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resources->config('demo.shared');
        $resources->config('only-in-b.key');
        $resources->config('demo.only-in-a');

        $queries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains(strval($query['query']), 'base_resource_override'));

        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    public function test_forgetting_a_bundle_makes_the_next_read_see_the_new_override(): void {
        $resources = $this->fixtures();

        $this->assertSame('from-a', $resources->config('demo.shared'));

        $this->overriding('cfg/demo', ['shared' => 'later']);
        $resources->forget();

        $this->assertSame('later', $resources->config('demo.shared'));
    }

    public function test_bundle_names_list_the_files_of_one_directory_only(): void {
        $this->useResourceFixtures();

        $names = app(Resources::class)->bundleNames('i18n/en');

        $this->assertContains('widget', $names);
        $this->assertContains('errors', $names);
        $this->assertNotContains('options', $names);
        $this->assertNotContains('template', $names);
    }

    public function test_bundle_names_merge_packages_without_repeating_a_name(): void {
        $this->useResourceFixtures();

        $names = app(Resources::class)->bundleNames('cfg');

        $sorted = $names;

        sort($sorted);

        $this->assertSame(array_values(array_unique($names)), $names);
        $this->assertSame(['system'], array_values(array_filter($names, fn (string $name): bool => $name === 'system')));
        $this->assertContains('dotted', $names);
        $this->assertSame($sorted, $names);
    }

    public function test_bundle_names_of_an_absent_directory_are_empty(): void {
        $this->useResourceFixtures();

        $this->assertSame([], app(Resources::class)->bundleNames('cfg/mail'));
    }

    public function test_a_style_bundle_declares_the_type_and_readonly_of_its_own_keys(): void {
        $this->useResourceFixtures();

        $schema = app(Resources::class)->getStyleBundle('cfg/gmail');

        $this->assertSame(['type' => 'text', 'readonly' => true], $schema['driver']);
        $this->assertSame('integer', $schema['port']['type']);
    }

    public function test_a_bundle_with_no_style_file_gets_an_empty_schema(): void {
        $this->useResourceFixtures();

        $this->assertSame([], app(Resources::class)->getStyleBundle('cfg/structured'));
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
