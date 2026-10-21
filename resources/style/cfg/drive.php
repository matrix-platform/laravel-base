<?php //>

return [

    'deduplicate' => ['type' => 'boolean', 'presentation' => 'switch'],

    'trash-default-days' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

];
