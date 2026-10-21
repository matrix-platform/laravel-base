<?php //>

namespace MatrixPlatform;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use MatrixPlatform\Console\Commands\ClearResourceCacheCommand;
use MatrixPlatform\Console\Commands\DispatchMessagesCommand;
use MatrixPlatform\Console\Commands\MakeCrudCommand;
use MatrixPlatform\Console\Commands\PruneTokensCommand;
use MatrixPlatform\Console\Commands\ResetUserPasswordCommand;
use MatrixPlatform\Console\Commands\RotateEncryptionKeyCommand;
use MatrixPlatform\Console\Commands\SetTelegramWebhookCommand;
use MatrixPlatform\Console\Commands\SyncTranslatableCommand;
use MatrixPlatform\Console\Commands\UpdateGeolocationDatabaseCommand;
use MatrixPlatform\Database\Schema\BaseBlueprint;
use MatrixPlatform\Http\Middleware\AnonymousThrottleMiddleware;
use MatrixPlatform\Http\Middleware\EncryptedEnvelopeMiddleware;
use MatrixPlatform\Http\Middleware\EnvelopeMiddleware;
use MatrixPlatform\Http\Middleware\LocaleMiddleware;
use MatrixPlatform\Http\Middleware\LoginThrottleMiddleware;
use MatrixPlatform\Http\Middleware\MemberAwareMiddleware;
use MatrixPlatform\Http\Middleware\MemberMiddleware;
use MatrixPlatform\Http\Middleware\PermissionMiddleware;
use MatrixPlatform\Http\Middleware\TelegramWebhookMiddleware;
use MatrixPlatform\Http\Middleware\UserAwareMiddleware;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Http\Middleware\VendorMiddleware;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Models\Generators\ConsoleRequest;
use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\PermissionTree;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\RollbackCallbacks;
use MatrixPlatform\Support\SharedModels;
use MatrixPlatform\Support\StagedContext;
use MatrixPlatform\Support\Variants;

class BaseServiceProvider extends ServiceProvider {

    public function boot(): void {
        if ($this->app->runningInConsole()) {
            ConsoleRequest::mark($this->app->make('request'));

            $this->commands([
                ClearResourceCacheCommand::class,
                DispatchMessagesCommand::class,
                MakeCrudCommand::class,
                PruneTokensCommand::class,
                ResetUserPasswordCommand::class,
                RotateEncryptionKeyCommand::class,
                SetTelegramWebhookCommand::class,
                SyncTranslatableCommand::class,
                UpdateGeolocationDatabaseCommand::class
            ]);
        }

        Validator::extend('password_format', function (string $attribute, mixed $value, array $parameters): bool {
            if (!array_key_exists(0, $parameters)) {
                throw new InvalidArgumentException('password_format requires a cfg bundle, e.g. password_format:admin');
            }

            $pattern = cfg("{$parameters[0]}.password-pattern");

            if (!is_string($pattern) || $pattern === '') {
                throw new InvalidArgumentException("cfg {$parameters[0]}.password-pattern is not defined");
            }

            return is_string($value) && preg_match($pattern, $value) === 1;
        });

        $packages = app(PackageRegistry::class);
        $packages->register('app', base_path());
        $packages->register('base', dirname(__DIR__));

        Route::aliasMiddleware('anonymous-throttle-api', AnonymousThrottleMiddleware::class);
        Route::aliasMiddleware('encrypted-api', EncryptedEnvelopeMiddleware::class);
        Route::aliasMiddleware('envelope-api', EnvelopeMiddleware::class);
        Route::aliasMiddleware('locale-api', LocaleMiddleware::class);
        Route::aliasMiddleware('login-throttle-api', LoginThrottleMiddleware::class);
        Route::aliasMiddleware('member-api', MemberMiddleware::class);
        Route::aliasMiddleware('member-aware-api', MemberAwareMiddleware::class);
        Route::aliasMiddleware('permission-api', PermissionMiddleware::class);
        Route::aliasMiddleware('telegram-webhook-api', TelegramWebhookMiddleware::class);
        Route::aliasMiddleware('user-api', UserMiddleware::class);
        Route::aliasMiddleware('user-aware-api', UserAwareMiddleware::class);
        Route::aliasMiddleware('vendor-api', VendorMiddleware::class);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/base.php');
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/matrix.php', 'matrix');

        $this->app->bind(Blueprint::class, fn (Application $app, array $parameters) => new BaseBlueprint(...$parameters));

        $this->app->scoped(Actor::class);
        $this->app->scoped(AdminPermission::class, fn () => new AdminPermission(actor()->requireUser(), app(Menus::class)));
        $this->app->scoped(PermissionTree::class);
        $this->app->scoped(Resources::class);
        $this->app->scoped(RollbackCallbacks::class);
        $this->app->scoped(StagedContext::class);
        $this->app->scoped(Variants::class);
        $this->app->singleton(Channels::class);
        $this->app->singleton(Menus::class);
        $this->app->singleton(MetadataRegistry::class);
        $this->app->singleton(PackageRegistry::class);
        $this->app->singleton(SharedModels::class);
    }

}
