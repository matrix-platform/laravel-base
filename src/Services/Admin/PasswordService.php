<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\RollbackCallbacks;

class PasswordService {

    private static ?string $dummyHash = null;

    public function confirm(User $user, string $password, string $field): void {
        $key = "matrix-confirm|{$user->id}";

        if (RateLimiter::tooManyAttempts($key, (int) cfg('admin.login-throttle-max'))) {
            error('too-many-requests', 429);
        }

        if (!$this->verify($user, $password)) {
            app(RollbackCallbacks::class)->persist(fn () => RateLimiter::hit($key, (int) cfg('admin.login-throttle-window') * 60));

            invalid($field, 'invalid-password');
        }

        RateLimiter::clear($key);
    }

    public function replace(User $user, string $password, ?string $preservedToken = null): void {
        $user->password = $password;
        $user->save();

        $this->revoke($user, $preservedToken);
    }

    public function revoke(User $user, ?string $preservedToken = null): void {
        $query = AuthToken::query()
            ->where('type', IdentityType::User)
            ->where('target_id', $user->id);

        if ($preservedToken !== null) {
            $query->where('token', '!=', AuthToken::digest($preservedToken));
        }

        foreach ($query->get(['id']) as $auth) {
            $auth->delete();
        }
    }

    public function verify(?User $user, string $password): bool {
        $hash = $user?->password;
        $verified = Hash::check($password, $hash ?: $this->dummyHash());

        return (bool) $hash && $verified;
    }

    private function dummyHash(): string {
        if (self::$dummyHash === null) {
            self::$dummyHash = Hash::make((string) Str::uuid());
        }

        return self::$dummyHash;
    }

}
