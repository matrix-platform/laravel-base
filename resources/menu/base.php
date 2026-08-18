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

        'setting' => ['icon' => 'fa-solid fa-gear', 'ranking' => 200, 'parent' => 'system'],

            'resource/cfg' => ['icon' => 'fa-solid fa-sliders', 'ranking' => 100, 'parent' => 'setting', 'group' => true, 'tag' => 'query'],

                'resource/cfg/get' => ['parent' => 'resource/cfg', 'tag' => 'query'],
                'resource/cfg/update' => ['parent' => 'resource/cfg', 'tag' => 'update'],

        'locale' => ['icon' => 'fa-solid fa-language', 'ranking' => 300, 'parent' => 'system'],

            'resource/i18n' => ['icon' => 'fa-solid fa-message', 'ranking' => 100, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/get' => ['parent' => 'resource/i18n', 'tag' => 'query'],
                'resource/i18n/update' => ['parent' => 'resource/i18n', 'tag' => 'update'],

            'resource/i18n/menu' => ['icon' => 'fa-solid fa-bars', 'ranking' => 200, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/menu/get' => ['parent' => 'resource/i18n/menu', 'tag' => 'query'],
                'resource/i18n/menu/update' => ['parent' => 'resource/i18n/menu', 'tag' => 'update'],

            'resource/i18n/options' => ['icon' => 'fa-solid fa-list-check', 'ranking' => 300, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/options/get' => ['parent' => 'resource/i18n/options', 'tag' => 'query'],
                'resource/i18n/options/update' => ['parent' => 'resource/i18n/options', 'tag' => 'update'],

            'resource/i18n/model' => ['icon' => 'fa-solid fa-table', 'ranking' => 400, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/model/get' => ['parent' => 'resource/i18n/model', 'tag' => 'query'],
                'resource/i18n/model/update' => ['parent' => 'resource/i18n/model', 'tag' => 'update'],

            'resource/i18n/template' => ['icon' => 'fa-solid fa-envelope-open-text', 'ranking' => 500, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/template/get' => ['parent' => 'resource/i18n/template', 'tag' => 'query'],
                'resource/i18n/template/update' => ['parent' => 'resource/i18n/template', 'tag' => 'update'],

];
