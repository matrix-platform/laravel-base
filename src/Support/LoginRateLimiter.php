<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class LoginRateLimiter {

    public function __invoke($request) {
        $max = cfg('admin.login-throttle-max');
        $minutes = cfg('admin.login-throttle-window');
        $username = $request->input('username');

        return Limit::perMinutes($minutes, $max)->by("{$request->ip()}|{$username}");
    }

}
