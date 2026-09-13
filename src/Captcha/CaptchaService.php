<?php //>

namespace MatrixPlatform\Captcha;

class CaptchaService {

    public function driver(string $provider): Driver {
        $driver = resolve_driver($provider, Driver::class, 'invalid-captcha-driver');

        if ($driver === null) {
            error('invalid-captcha-driver');
        }

        return $driver;
    }

}
