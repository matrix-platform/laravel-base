<?php //>

namespace MatrixPlatform\Routing;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Middleware\EncryptedEnvelopeMiddleware;
use MatrixPlatform\Support\Menus;
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

    /**
     * @param class-string $controller
     * @return class-string
     */
    private static function concrete(string $controller): string {
        return app()->bound($controller) ? app($controller)::class : $controller;
    }

    public static function controller(string $prefix): ?string {
        $stripped = strval(config('matrix.admin-api-prefix')) . '/';

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), $stripped) && $route->getAction('mount') === $prefix) {
                $action = $route->getAction('controller');

                return is_string($action) ? Str::before($action, '@') : null;
            }
        }

        return null;
    }

    public static function fallback(): void {
        Route::any('{endpoint}', fn () => error('endpoint-not-found', 404))->where('endpoint', '.*')->fallback();
    }

    /**
     * @return (Closure(string): bool)|null
     */
    private static function listed(): ?Closure {
        $group = Arr::last(Route::getGroupStack());

        if (!is_array($group) || !in_array('permission-api', Arr::wrap(array_get_value($group, 'middleware')), true)) {
            return null;
        }

        $menus = app(Menus::class);
        $prefix = trim(Route::getLastGroupPrefix(), '/');
        $admin = strval(config('matrix.admin-api-prefix')) . '/';

        if (!str_starts_with("{$prefix}/", $admin)) {
            throw new LogicException('permission-api can only be used under the admin api prefix');
        }

        $stripped = strlen($admin);

        return fn (string $path): bool => $menus->has(substr(trim("{$prefix}/{$path}", '/'), $stripped));
    }

    /**
     * @param class-string $controller
     */
    public static function mount(string $prefix, string $controller, ?string $scope = null): void {
        $outer = trim(Route::getLastGroupPrefix(), '/');
        $admin = strval(config('matrix.admin-api-prefix'));

        if ($outer !== $admin && str_starts_with("{$outer}/", "{$admin}/")) {
            throw new LogicException('mount() under the admin api prefix must be given the full path');
        }

        Route::prefix($prefix)->group(fn () => self::scan($controller, $scope, $prefix));
    }

    /**
     * @param class-string $controller
     * @return list<array{path: string, method: string, middleware: ?string, encrypted: bool}>
     */
    public static function resolve(string $controller, ?string $scope = null): array {
        $routes = [];

        foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $action = self::attribute($method);

            if ($action === null || $action->scope !== $scope) {
                continue;
            }

            $path = $action->path === null ? Str::kebab($method->getName()) : $action->path;

            $routes[] = ['path' => $path, 'method' => $method->getName(), 'middleware' => $action->middleware, 'encrypted' => $action->encrypted];
        }

        usort($routes, fn (array $first, array $second): int => $first['path'] <=> $second['path']);

        return $routes;
    }

    /**
     * @param class-string $controller
     */
    public static function scan(string $controller, ?string $scope = null, ?string $mount = null): void {
        $controller = self::concrete($controller);
        $listed = self::listed();
        $routes = self::resolve($controller, $scope);
        $literals = array_unique(array_map(fn (array $route): string => preg_quote(Str::before($route['path'], '/'), '/'), array_filter($routes, fn (array $route): bool => $route['path'] !== '' && !str_starts_with($route['path'], '{'))));

        foreach ($routes as $route) {
            if ($listed !== null && !$listed($route['path'])) {
                continue;
            }

            $registered = Route::post($route['path'], [$controller, $route['method']])->middleware(Arr::wrap($route['middleware']));

            if ($listed !== null && $literals !== [] && preg_match('/^\{(\w+)\}/', $route['path'], $matches) === 1) {
                $registered->where($matches[1], '(?!(?:' . implode('|', $literals) . ')(?![^\/]))[^\/]+');
            }

            if ($mount !== null) {
                $registered->setAction([...$registered->getAction(), 'mount' => $mount]);
            }

            if (!$route['encrypted']) {
                $registered->withoutMiddleware(EncryptedEnvelopeMiddleware::class);
            }
        }
    }

}
