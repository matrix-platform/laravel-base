<?php //>

namespace MatrixPlatform\Captcha;

class RecaptchaDriver implements Driver {

    use SiteVerification;

    private const DEFAULT_THRESHOLD = 0.5;

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

        $score = $response->json('score');
        $threshold = cfg("{$this->bundle}.threshold");

        return is_numeric($score) && floatval($score) >= (is_numeric($threshold) ? floatval($threshold) : self::DEFAULT_THRESHOLD);
    }

}
