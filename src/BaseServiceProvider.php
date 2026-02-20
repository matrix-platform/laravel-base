<?php //>

namespace MatrixPlatform;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use MatrixPlatform\Console\Commands\ResetUserPassword;
use MatrixPlatform\Http\Middleware\UserMiddleware;

class BaseServiceProvider extends ServiceProvider {

    public function boot(Router $router) {
        Carbon::serializeUsing(fn ($carbon) => $carbon->format('Y-m-d H:i:s'));

        if ($this->app->runningInConsole()) {
            Blueprint::macro('primaryKey', function () {
                $this->integer('id')->default(DB::raw('NEXTVAL(\'base_id\')'))->primary();
            });

            Blueprint::macro('schedules', function () {
                $this->timestamp('enable_time')->nullable();
                $this->timestamp('disable_time')->nullable();
            });

            $this->commands([ResetUserPassword::class]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        } else {
            Response::macro('success', fn ($data = null) => Response::json(['success' => true, 'data' => $data]));

            $this->loadRoutesFrom(__DIR__ . '/../routes/base.php');

            $router->aliasMiddleware('user-api', UserMiddleware::class);
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
