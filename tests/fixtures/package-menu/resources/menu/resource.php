<?php //>

return [

    'system' => ['icon' => 'fa-desktop', 'ranking' => 9000, 'parent' => null],

    'setting' => ['icon' => 'fa-gear', 'ranking' => 200, 'parent' => 'system'],

    'resource' => ['icon' => 'fa-file-lines', 'ranking' => 100, 'parent' => 'setting', 'group' => true, 'tag' => 'query'],

];
