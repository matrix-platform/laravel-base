<?php //>

use MatrixPlatform\Captcha\RecaptchaDriver;

return [

    'driver' => RecaptchaDriver::class,

    'action' => 'login',

    'endpoint' => 'https://www.google.com/recaptcha/api/siteverify',

    'hostnames' => '',

    'secret' => '',

    'threshold' => 0.5,

];
