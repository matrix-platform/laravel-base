<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Captcha;
use MatrixPlatform\Support\RollbackCallbacks;

class AuthService {

    private static function comparisonHash(?User $user): string {
        if ($user !== null && $user->password !== null) {
            return $user->password;
        }

        if (self::$placeholder === null) {
            self::$placeholder = Hash::make((string) Str::uuid());
        }

        return self::$placeholder;
    }

    private static ?string $placeholder = null;

    /**
     * @return array{token: string, image: string}
     */
    public function captcha(): array {
        $code = Str::password(5, false, true, false);
        $token = (string) Str::uuid();

        Cache::put("captcha:{$token}", hash('sha256', $code), (int) cfg('admin.captcha-ttl'));

        return ['token' => $token, 'image' => Captcha::generate($code)];
    }

    /**
     * @return array{token: string}
     */
    public function login(string $username, string $password, string $token, string $code): array {
        if (Cache::pull("captcha:{$token}") !== hash('sha256', $code)) {
            error('invalid-captcha', 422);
        }

        $user = User::query()
            ->where('username', $username)
            ->whereEnabled()
            ->first();

        $verified = Hash::check($password, self::comparisonHash($user));

        if ($user === null || !$verified) {
            if ($user !== null) {
                app(RollbackCallbacks::class)->register(fn () => $user->writeLog(UserLogType::LoginFailed));
            }

            error('invalid-username-or-password', 422);
        }

        $user->writeLog(UserLogType::Login);

        return ['token' => $user->createToken()];
    }

    public function logout(?string $token): void {
        $auth = AuthToken::findByToken($token, IdentityType::User);

        if ($auth === null) {
            return;
        }

        $auth->expire_time = now();
        $auth->save();

        user()?->writeLog(UserLogType::Logout);
    }

    public function passwd(User $user, string $current, string $password, ?string $token): void {
        if (!Hash::check($current, self::comparisonHash($user))) {
            error('invalid-password', 422);
        }

        $user->password = $password;
        $user->save();

        $this->revoke($user, $token);

        $user->writeLog(UserLogType::ChangePassword);
    }

    /**
     * @return array{nodes: array<string, array<string, mixed>>, profile: User}
     */
    public function profile(User $user): array {
        return ['nodes' => app(AdminPermission::class)->getMenuNodes(), 'profile' => $user->makeHidden('permissions')];
    }

    private function revoke(User $user, ?string $token): void {
        $query = AuthToken::query()
            ->where('type', IdentityType::User)
            ->where('target_id', $user->id);

        if ($token !== null) {
            $query->where('token', '!=', $token);
        }

        foreach ($query->get(['id']) as $auth) {
            $auth->delete();
        }
    }

}
