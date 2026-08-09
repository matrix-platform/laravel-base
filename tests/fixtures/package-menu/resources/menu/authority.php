<?php //>

return [

    'system' => ['icon' => 'fa-desktop', 'ranking' => 9000, 'parent' => null],

    'authority' => ['icon' => 'fa-users-gear', 'ranking' => 100, 'parent' => 'system'],

    'user' => ['icon' => 'fa-user', 'ranking' => 100, 'parent' => 'authority', 'group' => true, 'tag' => 'query'],

    'user/{id}' => ['parent' => 'user', 'tag' => 'query'],

    'user/{id}/update' => ['parent' => 'user', 'tag' => 'update'],

    'user/delete' => ['parent' => 'user', 'tag' => 'delete'],

    'user/insert' => ['parent' => 'user', 'tag' => 'insert'],

    'group' => ['icon' => 'fa-user-group', 'ranking' => 200, 'parent' => 'authority', 'group' => true, 'tag' => 'query'],

    'console' => ['ranking' => 300, 'parent' => 'system', 'group' => true, 'tag' => 'system'],

    'preference' => ['ranking' => 400, 'parent' => 'system', 'group' => true, 'tag' => 'user'],

    'report' => ['ranking' => 500, 'parent' => 'system', 'group' => true, 'tag' => 'export'],

    'report/run' => ['parent' => 'report', 'tag' => 'export'],

];
