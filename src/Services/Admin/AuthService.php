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

        Cache::put("captcha:{$token}", hash('sha256', $code), cfg('admin.captcha-ttl'));

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
                Event::listen(TransactionRolledBack::class, fn () => $user->writeLog('LoginFailed'));
            }

            error('invalid-username-or-password');
        }

        $user->writeLog('Login');

        return ['token' => $user->createToken()];
    }

    public function logout($token) {
        $auth = AuthToken::findByToken($token, 'User');

        if ($auth) {
            $auth->expire_time = now();
            $auth->save();

            $user = User::find($auth->target_id);

            if ($user) {
                $user->writeLog('Logout');
            }
        }
    }

    public function passwd($id, $current, $password) {
        $user = User::findOrFail($id);

        if (!Hash::check($current, $user->password)) {
            error('invalid-password');
        }

        $user->password = $password;
        $user->save();

        $user->writeLog('ChangePassword');
    }

    public function profile($user) {
        return ['nodes' => app(AdminPermission::class)->getMenuNodes(), 'profile' => $user];
    }

}
