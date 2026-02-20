<?php //>

namespace MatrixPlatform;

use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider {

    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

}
