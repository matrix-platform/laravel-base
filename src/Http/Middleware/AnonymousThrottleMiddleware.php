<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;

class AnonymousThrottleMiddleware {

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next, string $bundle): mixed {
        $name = "matrix-anonymous-{$bundle}";

        RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinutes((int) cfg("{$bundle}.anonymous-throttle-window"), (int) cfg("{$bundle}.anonymous-throttle-max"))->by($request->path() . '|' . $request->ip()));

        return $this->throttle->handle($request, $next, $name);
    }

}
