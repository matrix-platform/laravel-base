<?php //>

return [

    'secondary' => ['type' => 'boolean', 'presentation' => 'switch'],

    'severity' => ['type' => 'text', 'presentation' => 'select', 'options' => 'severity', 'rule' => ['in:default,error,info,primary,success,warning']],

    'url' => ['type' => 'text', 'readonly' => true],

];
