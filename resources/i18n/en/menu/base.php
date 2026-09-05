<?php //>

return [

    'basic' => 'Basic Data',

        'city' => 'Cities',

            'city/{id}' => 'City',
            'city/new' => 'New City',

            'city/{city_id}/area' => 'Districts',

                'city/{city_id}/area/{id}' => 'District',
                'city/{city_id}/area/new' => 'New District',

    'system' => 'System',

        'authority' => 'Authority',

            'user' => 'Accounts',

                'user/{id}' => 'Edit Account',
                'user/new' => 'New Account',

            'group' => 'Groups',

                'group/{id}' => 'Edit Group',
                'group/new' => 'New Group',

        'setting' => 'Settings',

            'resource/cfg' => 'General',

                'resource/cfg/{id}' => 'Edit setting',

        'locale' => 'Languages',

            'resource/i18n' => 'Messages',

                'resource/i18n/{id}' => 'Edit messages',

            'resource/i18n/menu' => 'Menus',

                'resource/i18n/menu/{id}' => 'Edit menu labels',

            'resource/i18n/options' => 'Options',

                'resource/i18n/options/{id}' => 'Edit option labels',

            'resource/i18n/model' => 'Tables',

                'resource/i18n/model/{id}' => 'Edit table labels',

            'resource/i18n/template' => 'Message templates',

                'resource/i18n/template/{id}' => 'Edit template',

            'translation' => 'Content Translation',

        'messaging' => 'Messaging',

            'mail-log' => 'Mail Logs',

                'mail-log/{id}' => 'Mail Log',

            'sms-log' => 'SMS Logs',

                'sms-log/{id}' => 'SMS Log',

            'push-log' => 'Push Logs',

                'push-log/{id}' => 'Push Log',

            'telegram-log' => 'Telegram Logs',

                'telegram-log/{id}' => 'Telegram Log',

        'geolocation' => 'Geolocation Lookup',

];
