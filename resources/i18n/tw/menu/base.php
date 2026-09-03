<?php //>

return [

    'basic' => '基本資料',

        'city' => '縣市',

            'city/{id}' => '縣市',
            'city/new' => '新增縣市',

            'city/{city_id}/area' => '行政區',

                'city/{city_id}/area/{id}' => '行政區',
                'city/{city_id}/area/new' => '新增行政區',

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

                'resource/cfg/{id}' => '編輯設定',

        'locale' => '多國語言',

            'resource/i18n' => '文字訊息',

                'resource/i18n/{id}' => '編輯文字訊息',

            'resource/i18n/menu' => '選單',

                'resource/i18n/menu/{id}' => '編輯選單文字',

            'resource/i18n/options' => '選項',

                'resource/i18n/options/{id}' => '編輯選項文字',

            'resource/i18n/model' => '資料表',

                'resource/i18n/model/{id}' => '編輯資料表文字',

            'resource/i18n/template' => '訊息樣板',

                'resource/i18n/template/{id}' => '編輯訊息樣板',

];
