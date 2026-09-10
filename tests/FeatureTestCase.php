<?php //>

namespace Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\ResourceGroup;
use MatrixPlatform\Support\Resources;
use Tests\Factories\UserFactory;

class FeatureTestCase extends TestCase {

    use RefreshDatabase;

    protected function actAsRoot(): void {
        actor()->setUser(UserFactory::new()->createOne(['id' => User::ROOT]));
    }

    protected function artisanCommand(string $command): PendingCommand {
        $pending = $this->artisan($command);

        if (!$pending instanceof PendingCommand) {
            $this->fail('artisan() did not return a pending command');
        }

        return $pending;
    }

    protected function captcha(string $code): string {
        $token = 'captcha-token';

        Cache::put("captcha:{$token}", hash('sha256', $code), 300);

        return $token;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    protected function columnByName(array $columns, string $name): array {
        foreach ($columns as $column) {
            if ($column['name'] === $name) {
                return $column;
            }
        }

        $this->fail("missing column {$name}");
    }

    protected function defineDatabaseMigrations(): void {
        $this->loadMigrationsFrom(__DIR__ . '/Stubs/migrations');
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('app.key', 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')));
        $app['config']->set('matrix.admin-api-encryption', false);
        $app['config']->set('matrix.api-encryption', false);
        $app['config']->set('matrix.vendor-api-encryption', false);

        Event::listen(RequestHandled::class, fn () => $app->forgetScopedInstances());
    }

    protected function queryCount(string $prefix, callable $callback): int {
        $matched = 0;

        DB::listen(function ($query) use ($prefix, &$matched): void {
            if (str_starts_with($query->sql, $prefix)) {
                $matched++;
            }
        });

        $callback();

        return $matched;
    }

    protected function refuses(string $slug, callable $callback): void {
        try {
            $callback();
        } catch (ServiceException $exception) {
            $this->assertSame($slug, $exception->getError());

            return;
        }

        $this->fail("expected the call to be refused with '{$slug}'");
    }

    protected function refusesField(string $field, string $slug, callable $callback): void {
        try {
            $callback();
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => [$field => [$slug]]], $exception->getExtra());

            return;
        }

        $this->fail("expected the call to be refused with {$field}:{$slug}");
    }

    /**
     * A 20x10 JPEG carrying an EXIF `Orientation=6` tag (needs a 90° rotation to be upright).
     */
    protected function rotatedImagePath(): string {
        $plain = tempnam(sys_get_temp_dir(), 'gd') . '.jpg';

        imagejpeg(imagecreatetruecolor(20, 10), $plain, 90);

        $tiff = 'II' . pack('v', 42) . pack('V', 8);
        $ifd0 = pack('v', 1) . pack('vvV', 0x0112, 3, 1) . pack('v', 6) . pack('v', 0) . pack('V', 0);
        $exif = "Exif\x00\x00" . $tiff . $ifd0;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        $bytes = file_get_contents($plain);

        if ($bytes === false) {
            $this->fail('failed to read the generated fixture image');
        }

        $path = tempnam(sys_get_temp_dir(), 'rot') . '.jpg';

        file_put_contents($path, substr($bytes, 0, 2) . $app1 . substr($bytes, 2));

        return $path;
    }

    protected function uploadedFrom(string $path, string $name): UploadedFile {
        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function useCfg(string $bundle, array $values): void {
        $override = new ResourceOverride();

        $override->bundle = "cfg/{$bundle}";
        $override->data = $values;

        $override->save();

        app(Resources::class)->forget();
    }

    protected function useCfgFixtures(): void {
        $this->usePackageFixtures('cfg-fixture', 'package-format');
    }

    protected function useDateFormats(string $date, string $datetime): void {
        config()->set('matrix.date-format', $date);
        config()->set('matrix.datetime-format', $datetime);
    }

    protected function useGeolocationFixtures(): void {
        $this->usePackageFixtures('geolocation-fixture', 'package-geolocation');
    }

    protected function useMenuFixtures(string $menus): void {
        $this->usePackageFixtures('menu-fixture', 'package-menu');

        $this->useMenus($menus);
    }

    protected function useMenus(?string $menus): void {
        config()->set('matrix.admin-menus', $menus);

        app()->forgetInstance(Menus::class);
    }

    protected function useMessagingFixtures(): void {
        $this->usePackageFixtures('messaging-fixture', 'package-messaging');
    }

    /**
     * @param array<string, list<string>> $groups
     */
    protected function useResourceWhitelist(array $groups): void {
        foreach (ResourceGroup::cases() as $group) {
            $names = array_get_value($groups, $group->value);

            config()->set("matrix.{$group->config()}", is_array($names) ? $names : []);
        }
    }

    protected function useResourceFixtures(): void {
        $this->usePackageFixtures('resource-fixture', 'package-resource');
    }

    protected function useTranslationFixtures(): void {
        $this->usePackageFixtures('translation-fixture', 'package-translation');
    }

    private function usePackageFixtures(string $package, string $directory): void {
        app(PackageRegistry::class)->register($package, __DIR__ . "/fixtures/{$directory}");

        config()->set('matrix.packages', "{$package} app base");

        app()->forgetInstance(Resources::class);
    }

}
