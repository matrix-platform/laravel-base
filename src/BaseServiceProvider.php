<?php //>

namespace MatrixPlatform;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Console\Commands\ResetUserPassword;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Support\AdminPermission;
use ReflectionClass;

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
            $router->aliasMiddleware('user-api', UserMiddleware::class);
        }

        if (!$this->app->routesAreCached()) {
            RouteRegistrar::macro('scan', function ($scope = null) {
                return $this->group(function () use ($scope) {
                    $controller = new ReflectionClass($this->attributes['controller']);

                    foreach ($controller->getMethods() as $method) {
                        $actions = $method->getAttributes(Action::class);

                        if ($actions && $actions[0]->newInstance()->scope === $scope) {
                            $name = $method->getName();

                            Route::post(Str::kebab($name), $name);
                        }
                    }
                });
            });
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/base.php');
    }

    public function register() {
        if (!$this->app->configurationIsCached()) {
            foreach (glob(__DIR__ . '/../config/*.php') as $file) {
                $this->mergeConfigFrom($file, basename($file, '.php'));
            }
        }

        $this->app->singleton("PackageInfo:base", fn () => new PackageInfo(dirname(__DIR__)));
        $this->app->singleton(AdminPermission::class, fn () => new AdminPermission(Request::user()));
        $this->app->singleton(Resources::class);
    }

}
