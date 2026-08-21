<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginRateLimiter {

    public function __construct(private string $bundle) {}

    public function __invoke(Request $request): Limit {
        $window = (int) cfg("{$this->bundle}.login-throttle-window");
        $max = (int) cfg("{$this->bundle}.login-throttle-max");
        $limit = Limit::perMinutes($window, $max)->by($request->ip() . '|' . $request->string('username')->value());

        $limit->afterCallback = fn (Response $response): bool => !$this->succeeded($response);

        return $limit;
    }

    private function succeeded(Response $response): bool {
        $body = json_decode((string) $response->getContent(), true);

        return is_array($body) && array_get_value($body, 'success') === true;
    }

}
