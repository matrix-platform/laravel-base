<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'bot-token' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'webhook-secret' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'interval' => ['type' => 'integer', 'rule' => ['integer', 'min:0']],

    'sandbox' => ['type' => 'boolean'],

];
