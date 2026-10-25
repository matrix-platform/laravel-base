<?php //>

return [

    'widget' => ['icon' => 'fa-cube', 'ranking' => 100, 'parent' => null, 'group' => true, 'tag' => 'query'],

    'widget/{id}' => ['parent' => 'widget', 'tag' => 'query'],

    'widget/{id}/update' => ['parent' => 'widget/{id}', 'tag' => 'update'],

];
