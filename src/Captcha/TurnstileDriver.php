<?php //>

namespace MatrixPlatform\Captcha;

class TurnstileDriver implements Driver {

    use SiteVerification;

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return ['token' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        return $this->siteverify('captcha-turnstile', $token) !== null;
    }

}
