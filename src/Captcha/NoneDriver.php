<?php //>

namespace MatrixPlatform\Captcha;

class NoneDriver implements Driver {

    public function generate(): ?array {
        return null;
    }

    public function rules(): array {
        return [];
    }

    public function verify(string $token, string $code): bool {
        return true;
    }

}
