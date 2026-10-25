<?php //>

namespace MatrixPlatform\Captcha;

class RecaptchaDriver implements Driver {

    use SiteVerification;

    public function __construct(private string $bundle = 'captcha-recaptcha') {}

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return ['token' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        $response = $this->siteverify($this->bundle, $token);

        if ($response === null) {
            return false;
        }

        return floatval($response->json('score')) >= floatval(cfg("{$this->bundle}.threshold"));
    }

}
