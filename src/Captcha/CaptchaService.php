<?php //>

namespace MatrixPlatform\Captcha;

class CaptchaService {

    public function driver(string $provider): Driver {
        $class = cfg("{$provider}.driver");

        if (!is_string($class) || !is_a($class, Driver::class, true)) {
            error('invalid-captcha-driver');
        }

        return app($class, ['bundle' => $provider]);
    }

}
