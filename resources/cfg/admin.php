<?php //>

return [

    'captcha-ttl' => 300,

    'login-throttle-max' => 5,

    'login-throttle-window' => 1,

    'mfa-challenge-ttl' => 300,

    'mfa-trust-days' => 30,

    'mfa-window' => 1,

    'passkey-allow-subdomains' => false,

    'passkey-challenge-ttl' => 120,

    'passkey-timeout' => 60000,

    'password-pattern' => '/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/',

    'token-idle-minutes' => 30,

];
