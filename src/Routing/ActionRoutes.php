<?php //>

namespace MatrixPlatform\Routing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Action;
use ReflectionClass;

class ActionRoutes {

    /**
     * @param class-string $controller
     * @return list<array{path: string, method: string, middleware: ?string}>
     */
    public static function resolve(string $controller, ?string $scope = null): array {
        $routes = [];

        foreach ((new ReflectionClass($controller))->getMethods() as $method) {
            $attributes = $method->getAttributes(Action::class);

            if ($attributes === []) {
                continue;
            }

            $action = $attributes[0]->newInstance();

            if ($action->scope !== $scope) {
                continue;
            }

            $routes[] = ['path' => $action->path ?: Str::kebab($method->getName()), 'method' => $method->getName(), 'middleware' => $action->middleware];
        }

        usort($routes, fn (array $first, array $second): int => $first['path'] <=> $second['path']);

        return $routes;
    }

    /**
     * @param class-string $controller
     */
    public static function scan(string $controller, ?string $scope = null): void {
        foreach (self::resolve($controller, $scope) as $route) {
            Route::post($route['path'], [$controller, $route['method']])->middleware(Arr::wrap($route['middleware']));
        }
    }

}
