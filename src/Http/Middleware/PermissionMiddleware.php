<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MatrixPlatform\Support\AdminPermission;

class PermissionMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        if (app(AdminPermission::class)->getCurrentMenu() === null) {
            error('permission-denied', 403);
        }

        return $next($request);
    }

}
