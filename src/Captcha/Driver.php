<?php //>

namespace MatrixPlatform\Captcha;

interface Driver {

    /**
     * @return array<string, mixed>|null
     */
    public function generate(): ?array;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array;

    public function verify(string $token, string $code): bool;

}
