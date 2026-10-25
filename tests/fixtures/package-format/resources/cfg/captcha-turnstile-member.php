<?php //>

use MatrixPlatform\Captcha\TurnstileDriver;

return [

    'driver' => TurnstileDriver::class,

    'action' => 'signup',

    'endpoint' => 'https://member.example.test/siteverify',

    'hostnames' => '',

    'secret' => 'member-secret',

];
