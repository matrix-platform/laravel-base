<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'api-key' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'package' => ['type' => 'text'],

    'endpoint' => ['type' => 'text', 'readonly' => true],

];
