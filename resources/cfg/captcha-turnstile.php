<?php //>

use MatrixPlatform\Captcha\TurnstileDriver;

return [

    'driver' => TurnstileDriver::class,

    'action' => 'login',

    'endpoint' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',

    'hostnames' => '',

    'secret' => '',

];
