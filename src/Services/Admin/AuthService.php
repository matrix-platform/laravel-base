<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Captcha;
use MatrixPlatform\Support\RollbackCallbacks;

class AuthService {

    public function __construct(private PasswordService $passwords, private MfaService $mfa) {}

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
     * @return array{token: string}|array{mfa: true, challenge: string}
     */
    public function login(string $username, string $password, string $token, string $code, ?string $trust): array {
        $expected = Cache::pull("captcha:{$token}");

        if (!is_string($expected) || !hash_equals($expected, hash('sha256', $code))) {
            invalid('code', 'invalid-captcha');
        }

        $user = $this->findUser($username);

        $verified = $this->passwords->verify($user, $password);

        if ($user === null || !$verified) {
            if ($user !== null) {
                app(RollbackCallbacks::class)->register(fn () => $user->writeLog(UserLogType::LoginFailed));
            }

            invalid('password', 'invalid-username-or-password');
        }

        if ($user->hasMfaEnabled() && !$this->mfa->trusted($user, $trust)) {
            $challenge = (string) Str::uuid();

            Cache::put("mfa-challenge:{$challenge}", $user->id, (int) cfg('admin.mfa-challenge-ttl'));

            return ['mfa' => true, 'challenge' => $challenge];
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

    /**
     * @return array{token: string, trust?: string}
     */
    public function mfa(string $username, string $challenge, string $code, bool $remember): array {
        $userId = Cache::get("mfa-challenge:{$challenge}");

        if ($userId === null) {
            invalid('code', 'invalid-challenge');
        }

        $user = $this->findUser($username);

        if ($user === null || $user->id !== (int) $userId) {
            invalid('code', 'invalid-challenge');
        }

        if (!$this->mfa->verify($user, $code)) {
            app(RollbackCallbacks::class)->register(fn () => $user->writeLog(UserLogType::MfaChallengeFailed));

            invalid('code', 'invalid-code');
        }

        Cache::forget("mfa-challenge:{$challenge}");
        $user->writeLog(UserLogType::Login);

        $data = ['token' => $user->createToken()];

        if ($remember) {
            $data['trust'] = $this->mfa->issueTrust($user);
        }

        return $data;
    }

    public function passwd(User $user, string $current, string $password, ?string $token): void {
        if (!$this->passwords->verify($user, $current)) {
            invalid('current', 'invalid-password');
        }

        $this->passwords->replace($user, $password, $token);

        $user->writeLog(UserLogType::ChangePassword);
    }

    /**
     * @return array{nodes: array<string, array<string, mixed>>, profile: ?User}
     */
    public function profile(?User $user): array {
        if ($user === null) {
            return ['nodes' => [], 'profile' => null];
        }

        return ['nodes' => app(AdminPermission::class)->getMenuNodes(), 'profile' => $user->makeHidden('permissions')];
    }

    private function findUser(string $username): ?User {
        return User::query()
            ->where('username', $username)
            ->whereEnabled()
            ->first();
    }

}
