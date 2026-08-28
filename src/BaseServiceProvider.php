<?php //>

namespace MatrixPlatform;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MatrixPlatform\Console\Commands\DispatchMessagesCommand;
use MatrixPlatform\Console\Commands\PruneTokensCommand;
use MatrixPlatform\Console\Commands\ResetUserPasswordCommand;
use MatrixPlatform\Database\Schema\BaseBlueprint;
use MatrixPlatform\Http\Middleware\EnvelopeMiddleware;
use MatrixPlatform\Http\Middleware\LocaleMiddleware;
use MatrixPlatform\Http\Middleware\LoginThrottleMiddleware;
use MatrixPlatform\Http\Middleware\MemberAwareMiddleware;
use MatrixPlatform\Http\Middleware\MemberMiddleware;
use MatrixPlatform\Http\Middleware\PermissionMiddleware;
use MatrixPlatform\Http\Middleware\UserAwareMiddleware;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Http\Middleware\VendorMiddleware;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\PermissionTree;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\RollbackCallbacks;

class BaseServiceProvider extends ServiceProvider {

    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([DispatchMessagesCommand::class, PruneTokensCommand::class, ResetUserPasswordCommand::class]);
        }

        Event::listen(TransactionRolledBack::class, fn () => app(RollbackCallbacks::class)->run());

        $packages = app(PackageRegistry::class);
        $packages->register('app', base_path());
        $packages->register('base', dirname(__DIR__));

        Route::aliasMiddleware('envelope-api', EnvelopeMiddleware::class);
        Route::aliasMiddleware('locale-api', LocaleMiddleware::class);
        Route::aliasMiddleware('login-throttle-api', LoginThrottleMiddleware::class);
        Route::aliasMiddleware('member-api', MemberMiddleware::class);
        Route::aliasMiddleware('member-aware-api', MemberAwareMiddleware::class);
        Route::aliasMiddleware('permission-api', PermissionMiddleware::class);
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
        $this->app->scoped(RollbackCallbacks::class);
        $this->app->singleton(Channels::class);
        $this->app->singleton(Menus::class);
        $this->app->singleton(MetadataRegistry::class);
        $this->app->singleton(PackageRegistry::class);
        $this->app->singleton(Resources::class);
    }

}
