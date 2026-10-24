<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'action' => ['type' => 'text', 'readonly' => true],

    'endpoint' => ['type' => 'text', 'readonly' => true],

    'hostnames' => ['type' => 'text'],

    'secret' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

];
