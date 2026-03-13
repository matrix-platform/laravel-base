<?php //>

namespace MatrixPlatform\Http\Middleware;

use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;

class UserMiddleware {

    public function handle($request, $next, $permission = null) {
        $auth = AuthToken::findByToken($request->bearerToken(), 'User');
        $user = $auth ? User::whereKey($auth->target_id)->where('disabled', false)->whereActive()->first() : null;

        if (!$user) {
            return ['success' => false, 'code' => 401, 'error' => 'invalid-token'];
        }

        if (!$auth->modify_time->isCurrentMinute()) {
            $auth->touch();
        }

        $request->setUserResolver(fn () => $user);

        actor()->user = $user;

        if ($permission === 'admin' && !app(AdminPermission::class)->getCurrentMenu()) {
            return ['success' => false, 'code' => 403, 'error' => 'permission-denied', 'message' => i18n('errors.permission-denied')];
        }

        return $next($request);
    }

}
