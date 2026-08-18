<?php //>

return [

    'login-throttle-max' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'login-throttle-window' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'token-idle-minutes' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

];
