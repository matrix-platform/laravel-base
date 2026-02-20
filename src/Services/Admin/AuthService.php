<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Captcha;

class AuthService {

    public function captcha() {
        $code = Str::password(5, false, true, false);
        $token = Str::uuid();

        Cache::put("captcha:{$token}", hash('sha256', $code), now()->addSeconds(cfg('admin.captcha-timeout')));

        return ['token' => $token, 'image' => Captcha::generate($code)];
    }

    public function login($username, $password, $token, $code) {
        $hash = Cache::pull("captcha:{$token}");

        if ($hash !== hash('sha256', $code)) {
            error('invalid-captcha');
        }

        $user = User::where('username', $username)->whereActive()->first();

        if (!$user || !Hash::check($password, $user->password)) {
            if ($user) {
                Event::listen(TransactionRolledBack::class, fn () => $user->writeLog(2)); //2:登入失敗
            }

            error('invalid-username-or-password');
        }

        $user->writeLog(1); //1:登入

        return ['token' => $user->createToken()];
    }

    public function logout($token) {
        $auth = AuthToken::findByToken($token, 1); //1:後台帳號

        if ($auth) {
            $auth->expire_time = now();
            $auth->save();

            $user = User::find($auth->target_id);

            if ($user) {
                $user->writeLog(9); //9:登出
            }
        }
    }

    public function passwd($id, $current, $password) {
        $user = User::find($id);

        if (!$user) {
            error('data-not-found');
        }

        if (!Hash::check($current, $user->password)) {
            error('invalid-password');
        }

        $pattern = cfg('admin.password-pattern');

        if ($pattern && !preg_match($pattern, $password)) {
            error('invalid-new-password');
        }

        $user->password = $password;
        $user->save();

        $user->writeLog(5); //5:變更密碼
    }

    public function profile($user) {
        return ['nodes' => app(AdminPermission::class)->getMenuNodes(), 'profile' => $user];
    }

}
