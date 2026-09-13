<?php //>

use MatrixPlatform\Captcha\RecaptchaDriver;

return [

    'driver' => RecaptchaDriver::class,

    'action' => 'login',

    'endpoint' => 'https://www.google.com/recaptcha/api/siteverify',

    'secret' => '',

    'threshold' => 0.5,

];
