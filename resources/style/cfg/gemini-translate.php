<?php //>

return [

    'driver' => ['type' => 'text', 'readonly' => true],

    'api-key' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'endpoint' => ['type' => 'text', 'readonly' => true],

    'model' => ['type' => 'text'],

    'prompt' => ['type' => 'text', 'presentation' => 'textarea', 'readonly' => true],

];
