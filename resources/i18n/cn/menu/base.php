<?php //>

return [

    'basic' => '共用类别',

        'city' => '城市',

            'city/{id}' => '编辑城市',
            'city/new' => '新增城市',

            'city/{city_id}/area' => '地区',

                'city/{city_id}/area/{id}' => '编辑地区',
                'city/{city_id}/area/new' => '新增地区',

    'system' => '系统管理',

        'authority' => '权限管理',

            'user' => '账号',

                'user/{id}' => '编辑账号',
                'user/new' => '新增账号',

            'group' => '群组',

                'group/{id}' => '编辑群组',
                'group/new' => '新增群组',

        'setting' => '系统设置',

            'resource/cfg' => '通用设置',

                'resource/cfg/{id}' => '编辑设置',

        'locale' => '多语言',

            'resource/i18n' => '文本消息',

                'resource/i18n/{id}' => '编辑文本消息',

            'resource/i18n/menu' => '菜单',

                'resource/i18n/menu/{id}' => '编辑菜单文本',

            'resource/i18n/options' => '选项',

                'resource/i18n/options/{id}' => '编辑选项文本',

            'resource/i18n/model' => '数据表',

                'resource/i18n/model/{id}' => '编辑数据表文本',

            'resource/i18n/template' => '消息模板',

                'resource/i18n/template/{id}' => '编辑消息模板',

            'translation' => '内容翻译',

        'messaging' => '消息管理',

            'mail-log' => '邮件记录',

                'mail-log/{id}' => '邮件记录',

            'sms-log' => '短信记录',

                'sms-log/{id}' => '短信记录',

            'push-log' => '推送记录',

                'push-log/{id}' => '推送记录',

            'telegram-log' => 'Telegram 记录',

                'telegram-log/{id}' => 'Telegram 记录',

        'geolocation' => '地理位置查询',

];
