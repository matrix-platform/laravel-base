<?php //>

return [

    'system' => '系統管理',

        'authority' => '權限管理',

            'user' => '帳號',

                'user/{id}' => '編輯帳號',
                'user/new' => '新增帳號',

            'group' => '群組',

                'group/{id}' => '編輯群組',
                'group/new' => '新增群組',

        'setting' => '系統設定',

            'resource/cfg' => '一般設定',

                'resource/cfg/get' => '編輯設定',

        'locale' => '多國語言',

            'resource/i18n' => '文字訊息',

                'resource/i18n/get' => '編輯文字訊息',

            'resource/i18n/menu' => '選單',

                'resource/i18n/menu/get' => '編輯選單文字',

            'resource/i18n/options' => '選項',

                'resource/i18n/options/get' => '編輯選項文字',

            'resource/i18n/model' => '資料表',

                'resource/i18n/model/get' => '編輯資料表文字',

            'resource/i18n/template' => '訊息樣板',

                'resource/i18n/template/get' => '編輯訊息樣板',

];
