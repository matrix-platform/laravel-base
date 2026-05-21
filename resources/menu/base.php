<?php //>

return [

    'system' => ['icon' => 'fa-solid fa-desktop', 'ranking' => 9000, 'parent' => null],

        'authority' => ['icon' => 'fa-solid fa-users-gear', 'ranking' => 100, 'parent' => 'system'],

            'user' => ['icon' => 'fa-solid fa-user', 'ranking' => 100, 'parent' => 'authority', 'group' => true, 'tag' => 'query'],

                'user/{id}' => ['parent' => 'user', 'tag' => 'query'],

                'user/{id}/update' => ['parent' => 'user', 'tag' => 'update'],

                'user/delete' => ['parent' => 'user', 'tag' => 'delete'],

                'user/insert' => ['parent' => 'user', 'tag' => 'insert'],

                'user/new' => ['parent' => 'user', 'tag' => 'insert'],

            'group' => ['icon' => 'fa-solid fa-user-group', 'ranking' => 200, 'parent' => 'authority', 'group' => true, 'tag' => 'query'],

                'group/{id}' => ['parent' => 'group', 'tag' => 'query'],

                'group/{id}/update' => ['parent' => 'group', 'tag' => 'update'],

                'group/delete' => ['parent' => 'group', 'tag' => 'delete'],

                'group/insert' => ['parent' => 'group', 'tag' => 'insert'],

                'group/new' => ['parent' => 'group', 'tag' => 'insert'],

];
