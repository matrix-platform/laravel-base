<?php //>

return [

    'captcha-ttl' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'login-throttle-max' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'login-throttle-window' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'mfa-challenge-ttl' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'mfa-trust-days' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'mfa-window' => ['type' => 'integer', 'rule' => ['integer', 'min:0']],

    'token-idle-minutes' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

];
