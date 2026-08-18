<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'port' => ['type' => 'integer', 'rule' => ['integer', 'min:1', 'max:65535']],

    'encryption' => ['type' => 'text', 'rule' => ['string', 'in:tls,ssl']],

    'from-address' => ['type' => 'text', 'rule' => ['email']],

    'interval' => ['type' => 'integer', 'rule' => ['integer', 'min:0']],

    'sandbox' => ['type' => 'boolean'],

    'sandbox-recipient' => ['type' => 'text', 'rule' => ['email']],

];
