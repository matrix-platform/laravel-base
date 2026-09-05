<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use MatrixPlatform\Models\User;
use PragmaRX\Google2FA\Google2FA;

class MfaService {

    public function __construct(private Google2FA $google2fa) {}

    public function confirm(User $user, string $code): void {
        if (!$this->verify($user, $code)) {
            invalid('code', 'invalid-code');
        }

        $user->confirmed_time = now();
        $user->save();
    }

    public function disable(User $user): void {
        $user->secret = null;
        $user->confirmed_time = null;
        $user->save();
    }

    public function issueTrust(User $user): string {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'expires' => now()->addDays((int) cfg('admin.mfa-trust-days'))->timestamp,
            'fingerprint' => $this->fingerprint($user)
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{secret: string, uri: string}
     */
    public function setup(User $user): array {
        if ($user->hasMfaEnabled()) {
            error('mfa-already-enabled');
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->secret = $secret;
        $user->save();

        return ['secret' => $secret, 'uri' => $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->username, $secret)];
    }

    public function trusted(User $user, ?string $token): bool {
        if ($token === null) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return false;
        }

        if (!is_array($payload)) {
            return false;
        }

        $expires = array_get_value($payload, 'expires');

        if (array_get_value($payload, 'user_id') !== $user->id || !is_int($expires)) {
            return false;
        }

        return $expires > now()->timestamp && hash_equals($this->fingerprint($user), strval(array_get_value($payload, 'fingerprint', '')));
    }

    public function verify(User $user, string $code): bool {
        $secret = $user->secret;

        return $secret !== null && (bool) $this->google2fa->verifyKey($secret, $code, (int) cfg('admin.mfa-window'));
    }

    private function fingerprint(User $user): string {
        return hash('sha256', $user->password . '|' . $user->secret);
    }

}
