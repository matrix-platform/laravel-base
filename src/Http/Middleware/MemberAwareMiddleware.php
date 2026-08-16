<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MemberAwareMiddleware extends MemberMiddleware {

    protected function refuse(Request $request, Closure $next): mixed {
        return $next($request);
    }

}
