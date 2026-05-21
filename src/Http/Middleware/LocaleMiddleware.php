<?php //>

namespace MatrixPlatform\Http\Middleware;

class LocaleMiddleware {

    public function handle($request, $next) {
        $header = $request->header('Matrix-Locale');

        app()->setLocale(in_array($header, config('matrix.locales')) ? $header : config('app.locale'));

        return $next($request);
    }

}
