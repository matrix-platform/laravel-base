<?php //>

return [

    'avatar' => ['type' => 'json', 'presentation' => 'drive-image'],

    'code' => ['type' => 'text', 'readonly' => true],

    'digest' => ['type' => 'boolean'],

    'locale' => ['type' => 'text', 'presentation' => 'select', 'rule' => ['string', 'in:tw,en']],

    'page-size' => ['type' => 'integer', 'rule' => ['integer', 'min:1']],

    'token' => ['type' => 'text', 'secret' => true],

];
