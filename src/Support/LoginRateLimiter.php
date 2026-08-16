<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class LoginRateLimiter {

    public function __construct(private string $bundle) {}

    public function __invoke(Request $request): Limit {
        $window = (int) cfg("{$this->bundle}.login-throttle-window");
        $max = (int) cfg("{$this->bundle}.login-throttle-max");

        return Limit::perMinutes($window, $max)->by($request->ip() . '|' . $request->string('username')->value());
    }

}
