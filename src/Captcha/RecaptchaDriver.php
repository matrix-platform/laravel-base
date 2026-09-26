<?php //>

namespace MatrixPlatform\Captcha;

class RecaptchaDriver implements Driver {

    use SiteVerification;

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return ['token' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        $response = $this->siteverify('captcha-recaptcha', $token);

        if ($response === null) {
            return false;
        }

        return floatval($response->json('score')) >= floatval(cfg('captcha-recaptcha.threshold'));
    }

}
