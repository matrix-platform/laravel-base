<?php //>

return [

    'hostnames' => ['type' => 'text'],

    'secret' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],

    'threshold' => ['type' => 'float', 'rule' => ['numeric', 'between:0,1']],

];
