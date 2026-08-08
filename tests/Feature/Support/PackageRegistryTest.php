<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\PackageRegistry;
use Tests\FeatureTestCase;

class PackageRegistryTest extends FeatureTestCase {

    private function registry(): PackageRegistry {
        $packages = new PackageRegistry();

        $packages->register('app', '/app');
        $packages->register('base', '/base');

        return $packages;
    }

    public function test_registered_path_is_returned_by_name(): void {
        $packages = new PackageRegistry();

        $packages->register('base', '/one');

        $this->assertSame('/one', $packages->path('base'));
    }

    public function test_registering_the_same_name_twice_replaces_the_path(): void {
        $packages = new PackageRegistry();

        $packages->register('base', '/one');
        $packages->register('base', '/two');

        $this->assertSame('/two', $packages->path('base'));
    }

    public function test_trailing_slashes_are_stripped(): void {
        $packages = new PackageRegistry();

        $packages->register('base', '/one/');

        $this->assertSame('/one', $packages->path('base'));
    }

    public function test_unknown_package_is_rejected(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown-package');

        (new PackageRegistry())->path('ghost');
    }

    public function test_paths_follow_the_order_of_the_config_list(): void {
        $packages = $this->registry();

        config()->set('matrix.packages', 'app base');

        $this->assertSame(['/app', '/base'], $packages->paths());

        config()->set('matrix.packages', 'base app');

        $this->assertSame(['/base', '/app'], $packages->paths());
    }

    public function test_paths_omit_packages_missing_from_the_config_list(): void {
        $packages = $this->registry();

        config()->set('matrix.packages', 'base');

        $this->assertSame(['/base'], $packages->paths());
    }

    public function test_paths_are_empty_when_the_config_list_is_empty(): void {
        $packages = $this->registry();

        config()->set('matrix.packages', '');

        $this->assertSame([], $packages->paths());
    }

    public function test_config_list_naming_an_unregistered_package_is_rejected(): void {
        $packages = $this->registry();

        config()->set('matrix.packages', 'app ghost');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown-package');

        $packages->paths();
    }

}
