<?php //>

namespace MatrixPlatform;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\RollbackCallbacks;

class BaseServiceProvider extends ServiceProvider {

    public function boot(): void {
        Event::listen(TransactionRolledBack::class, fn () => app(RollbackCallbacks::class)->run());

        $packages = app(PackageRegistry::class);
        $packages->register('app', base_path());
        $packages->register('base', dirname(__DIR__));
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/matrix.php', 'matrix');

        $this->app->scoped(RollbackCallbacks::class);
        $this->app->singleton(PackageRegistry::class);
        $this->app->singleton(Resources::class);
    }

}
