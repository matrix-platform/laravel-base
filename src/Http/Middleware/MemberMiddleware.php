<?php //>

namespace MatrixPlatform\Http\Middleware;

use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\Member;

class MemberMiddleware {

    public function handle($request, $next) {
        $auth = AuthToken::findByToken($request->bearerToken(), 'Member');
        $member = $auth ? Member::whereKey($auth->target_id)->where('status', 1)->first() : null;

        if (!$member) {
            return ['success' => false, 'code' => 401, 'error' => 'invalid-token'];
        }

        if (!$auth->modify_time->isCurrentMinute()) {
            $auth->touch();
        }

        $request->setUserResolver(fn () => $member);

        actor()->member = $member;

        return $next($request);
    }

}
