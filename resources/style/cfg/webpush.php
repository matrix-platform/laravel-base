<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'private-key' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'interval' => ['type' => 'integer', 'rule' => ['integer', 'min:0']],

    'sandbox' => ['type' => 'boolean', 'presentation' => 'switch'],

];
