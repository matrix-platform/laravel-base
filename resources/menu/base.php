<?php //>

return [

    'basic' => ['icon' => 'fa-solid fa-database', 'ranking' => 8000, 'parent' => null],

        'city' => ['icon' => 'fa-solid fa-city', 'ranking' => 100, 'parent' => 'basic', 'group' => true, 'tag' => 'query'],

            'city/{id}' => ['parent' => 'city', 'tag' => 'query'],
            'city/{id}/update' => ['parent' => 'city', 'tag' => 'update'],
            'city/delete' => ['parent' => 'city', 'tag' => 'delete'],
            'city/insert' => ['parent' => 'city', 'tag' => 'insert'],
            'city/new' => ['parent' => 'city', 'tag' => 'insert'],
            'city/sort' => ['parent' => 'city', 'tag' => 'update'],
            'city/sort/save' => ['parent' => 'city', 'tag' => 'update'],

            'city/{city_id}/area' => ['icon' => 'fa-solid fa-location-dot', 'parent' => 'city', 'group' => true, 'tag' => 'query'],

                'city/{city_id}/area/{id}' => ['parent' => 'city/{city_id}/area', 'tag' => 'query'],
                'city/{city_id}/area/{id}/update' => ['parent' => 'city/{city_id}/area', 'tag' => 'update'],
                'city/{city_id}/area/delete' => ['parent' => 'city/{city_id}/area', 'tag' => 'delete'],
                'city/{city_id}/area/insert' => ['parent' => 'city/{city_id}/area', 'tag' => 'insert'],
                'city/{city_id}/area/new' => ['parent' => 'city/{city_id}/area', 'tag' => 'insert'],
                'city/{city_id}/area/sort' => ['parent' => 'city/{city_id}/area', 'tag' => 'update'],
                'city/{city_id}/area/sort/save' => ['parent' => 'city/{city_id}/area', 'tag' => 'update'],

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

                'resource/cfg/{id}' => ['parent' => 'resource/cfg', 'tag' => 'query'],
                'resource/cfg/{id}/update' => ['parent' => 'resource/cfg', 'tag' => 'update'],

        'locale' => ['icon' => 'fa-solid fa-language', 'ranking' => 300, 'parent' => 'system'],

            'resource/i18n' => ['icon' => 'fa-solid fa-message', 'ranking' => 100, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/{id}' => ['parent' => 'resource/i18n', 'tag' => 'query'],
                'resource/i18n/{id}/update' => ['parent' => 'resource/i18n', 'tag' => 'update'],

            'resource/i18n/menu' => ['icon' => 'fa-solid fa-bars', 'ranking' => 200, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/menu/{id}' => ['parent' => 'resource/i18n/menu', 'tag' => 'query'],
                'resource/i18n/menu/{id}/update' => ['parent' => 'resource/i18n/menu', 'tag' => 'update'],

            'resource/i18n/options' => ['icon' => 'fa-solid fa-list-check', 'ranking' => 300, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/options/{id}' => ['parent' => 'resource/i18n/options', 'tag' => 'query'],
                'resource/i18n/options/{id}/update' => ['parent' => 'resource/i18n/options', 'tag' => 'update'],

            'resource/i18n/model' => ['icon' => 'fa-solid fa-table', 'ranking' => 400, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/model/{id}' => ['parent' => 'resource/i18n/model', 'tag' => 'query'],
                'resource/i18n/model/{id}/update' => ['parent' => 'resource/i18n/model', 'tag' => 'update'],

            'resource/i18n/template' => ['icon' => 'fa-solid fa-envelope-open-text', 'ranking' => 500, 'parent' => 'locale', 'group' => true, 'tag' => 'query'],

                'resource/i18n/template/{id}' => ['parent' => 'resource/i18n/template', 'tag' => 'query'],
                'resource/i18n/template/{id}/update' => ['parent' => 'resource/i18n/template', 'tag' => 'update'],

        'messaging' => ['icon' => 'fa-solid fa-paper-plane', 'ranking' => 400, 'parent' => 'system'],

            'mail-log' => ['icon' => 'fa-solid fa-envelope', 'ranking' => 100, 'parent' => 'messaging', 'group' => true, 'tag' => 'query'],

                'mail-log/{id}' => ['parent' => 'mail-log', 'tag' => 'query'],
                'mail-log/{id}/resend' => ['parent' => 'mail-log', 'tag' => 'update'],
                'mail-log/{id}/cancel' => ['parent' => 'mail-log', 'tag' => 'update'],

            'sms-log' => ['icon' => 'fa-solid fa-comment-sms', 'ranking' => 200, 'parent' => 'messaging', 'group' => true, 'tag' => 'query'],

                'sms-log/{id}' => ['parent' => 'sms-log', 'tag' => 'query'],
                'sms-log/{id}/resend' => ['parent' => 'sms-log', 'tag' => 'update'],
                'sms-log/{id}/cancel' => ['parent' => 'sms-log', 'tag' => 'update'],

            'push-log' => ['icon' => 'fa-solid fa-bell', 'ranking' => 300, 'parent' => 'messaging', 'group' => true, 'tag' => 'query'],

                'push-log/{id}' => ['parent' => 'push-log', 'tag' => 'query'],
                'push-log/{id}/resend' => ['parent' => 'push-log', 'tag' => 'update'],
                'push-log/{id}/cancel' => ['parent' => 'push-log', 'tag' => 'update'],

            'telegram-log' => ['icon' => 'fa-solid fa-comment', 'ranking' => 400, 'parent' => 'messaging', 'group' => true, 'tag' => 'query'],

                'telegram-log/{id}' => ['parent' => 'telegram-log', 'tag' => 'query'],
                'telegram-log/{id}/resend' => ['parent' => 'telegram-log', 'tag' => 'update'],
                'telegram-log/{id}/cancel' => ['parent' => 'telegram-log', 'tag' => 'update'],

        'geolocation' => ['icon' => 'fa-solid fa-location-crosshairs', 'ranking' => 500, 'parent' => 'system', 'group' => true, 'tag' => 'query'],

];
