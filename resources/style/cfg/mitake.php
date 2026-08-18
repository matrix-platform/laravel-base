<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'endpoint' => ['type' => 'text', 'rule' => ['url']],

    'interval' => ['type' => 'integer', 'rule' => ['integer', 'min:0']],

    'sandbox' => ['type' => 'boolean'],

];
