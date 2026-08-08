<?php //>

namespace MatrixPlatform;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MatrixPlatform\Database\Schema\BaseBlueprint;
use MatrixPlatform\Http\Middleware\EnvelopeMiddleware;
use MatrixPlatform\Http\Middleware\LocaleMiddleware;
use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\RollbackCallbacks;

class BaseServiceProvider extends ServiceProvider {

    public function boot(): void {
        Event::listen(TransactionRolledBack::class, fn () => app(RollbackCallbacks::class)->run());

        $packages = app(PackageRegistry::class);
        $packages->register('app', base_path());
        $packages->register('base', dirname(__DIR__));

        Route::aliasMiddleware('envelope-api', EnvelopeMiddleware::class);
        Route::aliasMiddleware('locale-api', LocaleMiddleware::class);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/matrix.php', 'matrix');

        $this->app->bind(Blueprint::class, fn (Application $app, array $parameters) => new BaseBlueprint(...$parameters));

        $this->app->scoped(Actor::class);
        $this->app->scoped(RollbackCallbacks::class);
        $this->app->singleton(PackageRegistry::class);
        $this->app->singleton(Resources::class);
    }

}
