<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use MatrixPlatform\Support\LoginRateLimiter;

class LoginThrottleMiddleware {

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next, string $bundle): mixed {
        $name = "matrix-login-{$bundle}";

        RateLimiter::for($name, (new LoginRateLimiter($bundle))(...));

        return $this->throttle->handle($request, $next, $name);
    }

}
