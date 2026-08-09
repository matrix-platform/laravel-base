<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class LoginRateLimiter {

    public function __invoke(Request $request): Limit {
        $window = (int) cfg('admin.login-throttle-window');
        $max = (int) cfg('admin.login-throttle-max');

        return Limit::perMinutes($window, $max)->by($request->ip() . '|' . $request->string('username')->value());
    }

}
