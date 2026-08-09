<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MatrixPlatform\Http\IdentityToken;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;

class UserMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        $auth = AuthToken::findByToken(IdentityToken::from($request, IdentityType::User), IdentityType::User);
        $user = $auth === null ? null : User::query()->whereKey($auth->target_id)->whereEnabled()->first();

        if ($auth === null || $user === null) {
            error('invalid-token', 401);
        }

        $auth->keepAlive();

        $request->setUserResolver(fn () => $user);

        actor()->setUser($user);

        return $next($request);
    }

}
