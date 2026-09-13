<?php //>

namespace MatrixPlatform\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MatrixPlatform\Support\Captcha;

class ImageDriver implements Driver {

    /**
     * @return array{token: string, image: string}
     */
    public function generate(): array {
        $code = Str::password(5, false, true, false);
        $token = (string) Str::uuid();

        Cache::put("captcha:{$token}", hash('sha256', $code), (int) cfg('admin.captcha-ttl'));

        return ['token' => $token, 'image' => Captcha::generate($code)];
    }

    public function rules(): array {
        return ['token' => ['required'], 'code' => ['required']];
    }

    public function verify(string $token, string $code): bool {
        $expected = Cache::pull("captcha:{$token}");

        return is_string($expected) && hash_equals($expected, hash('sha256', $code));
    }

}
