<?php //>

return [

    'navigate' => ['type' => 'boolean', 'presentation' => 'switch', 'readonly' => true],

    'severity' => ['type' => 'text', 'presentation' => 'select', 'options' => 'severity', 'rule' => ['in:default,error,info,primary,success,warning']],

    'url' => ['type' => 'text', 'readonly' => true],

];
