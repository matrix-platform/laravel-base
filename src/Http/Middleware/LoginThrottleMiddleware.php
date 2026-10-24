<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use MatrixPlatform\Support\LoginRateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottleMiddleware {

    public function handle(Request $request, Closure $next, string $bundle): mixed {
        $name = "matrix-login-{$bundle}";

        $limiter = new LoginRateLimiter($bundle);

        RateLimiter::for($name, $limiter(...));

        $limit = $limiter($request);
        $key = "{$name}|{$limit->key}";

        if (RateLimiter::increment($key, $limit->decaySeconds) > $limit->maxAttempts) {
            throw new ThrottleRequestsException('Too Many Attempts.');
        }

        $response = $next($request);

        if ($response instanceof Response && $this->succeeded($response)) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    private function succeeded(Response $response): bool {
        $body = json_decode((string) $response->getContent(), true);

        return is_array($body) && array_get_value($body, 'success') === true;
    }

}
