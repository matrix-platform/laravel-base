<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocaleMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        app()->setLocale($this->resolve($request->header('Matrix-Locale')));

        return $next($request);
    }

    /**
     * @param string|list<string|null>|null $header
     */
    private function resolve(string|array|null $header): string {
        if (is_string($header) && in_array($header, locales(), true)) {
            return $header;
        }

        return app()->getLocale();
    }

}
