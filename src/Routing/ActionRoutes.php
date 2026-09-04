<?php //>

namespace MatrixPlatform\Routing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Action;
use ReflectionClass;
use ReflectionMethod;

class ActionRoutes {

    public static function attribute(ReflectionMethod $method): ?Action {
        $name = $method->getName();

        while (true) {
            $attributes = $method->getAttributes(Action::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }

            $parent = $method->getDeclaringClass()->getParentClass();

            if ($parent === false || !$parent->hasMethod($name)) {
                return null;
            }

            $method = $parent->getMethod($name);
        }
    }

    public static function fallback(): void {
        Route::any('{endpoint}', fn () => error('endpoint-not-found', 404))->where('endpoint', '.*')->fallback();
    }

    /**
     * @param class-string $controller
     */
    public static function mount(string $prefix, string $controller, ?string $scope = null): void {
        Route::prefix($prefix)->group(fn () => self::scan($controller, $scope));
    }

    /**
     * @param class-string $controller
     * @return list<array{path: string, method: string, middleware: ?string}>
     */
    public static function resolve(string $controller, ?string $scope = null): array {
        $routes = [];

        foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $action = self::attribute($method);

            if ($action === null || $action->scope !== $scope) {
                continue;
            }

            $path = $action->path === null ? Str::kebab($method->getName()) : $action->path;

            $routes[] = ['path' => $path, 'method' => $method->getName(), 'middleware' => $action->middleware];
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
