<?php //>

namespace MatrixPlatform;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider {

    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        } else {
            Response::macro('success', fn ($data = null) => Response::json(['success' => true, 'data' => $data]));
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
