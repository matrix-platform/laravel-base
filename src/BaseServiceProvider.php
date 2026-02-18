<?php //>

namespace MatrixPlatform;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider {

    public function boot() {
        Carbon::serializeUsing(fn ($carbon) => $carbon->format('Y-m-d H:i:s'));

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    public function register() {
        if (!$this->app->configurationIsCached()) {
            foreach (glob(__DIR__ . '/../config/*.php') as $file) {
                $this->mergeConfigFrom($file, basename($file, '.php'));
            }
        }

        $this->app->singleton("PackageInfo:base", fn () => new PackageInfo(dirname(__DIR__)));
        $this->app->singleton(Resources::class);
    }

}
