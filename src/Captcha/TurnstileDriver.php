<?php //>

namespace MatrixPlatform\Captcha;

use Illuminate\Support\Facades\Http;

class TurnstileDriver implements Driver {

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 5;

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return ['token' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        $endpoint = strval(cfg('captcha-turnstile.endpoint'));
        $secret = strval(cfg('captcha-turnstile.secret'));

        $response = Http::asForm()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->post($endpoint, ['secret' => $secret, 'response' => $token]);

        if ($response->failed()) {
            error('captcha-request-failed');
        }

        if ($response->json('success') !== true || $response->json('action') !== strval(cfg('captcha-turnstile.action'))) {
            return false;
        }

        return $this->permitted($response->json('hostname'));
    }

    private function permitted(mixed $hostname): bool {
        $allowed = tokenize(strval(cfg('captcha-turnstile.hostnames')));

        return $allowed === [] || in_array(strval($hostname), $allowed, true);
    }

}
