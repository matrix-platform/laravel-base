<?php //>

namespace MatrixPlatform;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MatrixPlatform\Support\RollbackCallbacks;

class BaseServiceProvider extends ServiceProvider {

    public function boot(): void {
        Event::listen(TransactionRolledBack::class, fn () => app(RollbackCallbacks::class)->run());
    }

    public function register(): void {
        $this->app->scoped(RollbackCallbacks::class);
    }

}
