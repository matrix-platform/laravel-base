<?php //>

namespace MatrixPlatform;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Console\Commands\PruneCommand;
use MatrixPlatform\Console\Commands\ResetUserPassword;
use MatrixPlatform\Http\Middleware\LocaleMiddleware;
use MatrixPlatform\Http\Middleware\MemberAwareMiddleware;
use MatrixPlatform\Http\Middleware\MemberMiddleware;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Support\Actor;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\LoginRateLimiter;
use MatrixPlatform\Support\PackageInfo;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\RollbackCallbacks;
use ReflectionClass;

class BaseServiceProvider extends ServiceProvider {

    public function boot(Router $router) {
        Carbon::serializeUsing(fn ($carbon) => $carbon->format('Y-m-d H:i:s'));

        Event::listen(TransactionRolledBack::class, fn () => app(RollbackCallbacks::class)->run());

        RateLimiter::for('matrix-login', Closure::fromCallable(new LoginRateLimiter()));

        if ($this->app->runningInConsole()) {
            Blueprint::macro('primaryKey', function () {
                $this->integer('id')->default(DB::raw('NEXTVAL(\'base_id\')'))->primary();
            });

            Blueprint::macro('ranking', function () {
                $this->integer('ranking')->default(DB::raw('NEXTVAL(\'base_ranking\')'));
            });

            Blueprint::macro('schedules', function () {
                $this->timestamp('enable_time')->nullable();
                $this->timestamp('disable_time')->nullable();
            });

            $this->commands([PruneCommand::class, ResetUserPassword::class]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $router->aliasMiddleware('locale-api', LocaleMiddleware::class);
        $router->aliasMiddleware('member-api', MemberMiddleware::class);
        $router->aliasMiddleware('member-aware-api', MemberAwareMiddleware::class);
        $router->aliasMiddleware('user-api', UserMiddleware::class);

        if (!$this->app->routesAreCached()) {
            RouteRegistrar::macro('scan', function ($scope = null) {
                return $this->group(function () use ($scope) {
                    $controller = new ReflectionClass($this->attributes['controller']);
                    $routes = [];

                    foreach ($controller->getMethods() as $method) {
                        $actions = $method->getAttributes(Action::class);

                        if ($actions) {
                            $action = $actions[0]->newInstance();

                            if ($action->scope === $scope) {
                                $path = $action->path !== null ? $action->path : Str::kebab($method->getName());

                                $routes[$path] = ['method' => $method->getName(), 'middleware' => $action->middleware];
                            }
                        }
                    }

                    ksort($routes);

                    foreach ($routes as $path => $info) {
                        $route = Route::post($path, $info['method']);

                        if ($info['middleware']) {
                            $route->middleware($info['middleware']);
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

        $this->app->scoped(Actor::class);
        $this->app->scoped(AdminPermission::class, fn () => new AdminPermission(Request::user()));
        $this->app->scoped(RollbackCallbacks::class);
        $this->app->singleton("PackageInfo:base", fn () => new PackageInfo(dirname(__DIR__)));
        $this->app->singleton(Resources::class);
    }

}
