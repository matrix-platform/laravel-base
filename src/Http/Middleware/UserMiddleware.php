<?php //>

namespace MatrixPlatform\Http\Middleware;

use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;

class UserMiddleware {

    public function handle($request, $next, $permission = null) {
        $auth = AuthToken::findByToken($request->bearerToken(), 1);
        $user = $auth ? User::whereKey($auth->target_id)->where('disabled', false)->whereActive()->first() : null;

        if (!$user) {
            return response()->json(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
        }

        $auth->modify_time = now();
        $auth->save();

        $request->setUserResolver(fn () => $user);

        define('USER_ID', $user->id);
        define('USER_LEVEL', USER_ID > 1000 ? 3 : (USER_ID > 1 ? 2 : 1));

        if ($permission === 'admin' && !app(AdminPermission::class)->getCurrentMenu()) {
            return response()->json(['success' => false, 'code' => 403, 'error' => 'permission-denied', 'message' => i18n('errors.permission-denied')]);
        }

        return $next($request);
    }

}
