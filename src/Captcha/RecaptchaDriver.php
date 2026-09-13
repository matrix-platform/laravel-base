<?php //>

namespace MatrixPlatform\Captcha;

use Illuminate\Support\Facades\Http;

class RecaptchaDriver implements Driver {

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 5;

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return ['token' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        $endpoint = strval(cfg('captcha-recaptcha.endpoint'));
        $secret = strval(cfg('captcha-recaptcha.secret'));

        $response = Http::asForm()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->post($endpoint, ['secret' => $secret, 'response' => $token]);

        if ($response->failed()) {
            error('captcha-request-failed');
        }

        if ($response->json('success') !== true || $response->json('action') !== strval(cfg('captcha-recaptcha.action'))) {
            return false;
        }

        return floatval($response->json('score')) >= floatval(cfg('captcha-recaptcha.threshold'));
    }

}
