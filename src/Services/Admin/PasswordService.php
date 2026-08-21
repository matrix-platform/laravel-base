<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;

class PasswordService {

    private static ?string $dummyHash = null;

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
            $query->where('token', '!=', $preservedToken);
        }

        foreach ($query->get(['id']) as $auth) {
            $auth->delete();
        }
    }

    public function verify(?User $user, string $password): bool {
        $hash = $user === null ? null : $user->password;
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
