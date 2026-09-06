<?php //>

return [

    'basic' => '共通カテゴリ',

        'city' => '都市',

            'city/{id}' => '都市編集',
            'city/new' => '都市の新規作成',

            'city/{city_id}/area' => '地区',

                'city/{city_id}/area/{id}' => '地区編集',
                'city/{city_id}/area/new' => '地区の新規作成',

    'system' => 'システム管理',

        'authority' => '権限管理',

            'user' => 'アカウント',

                'user/{id}' => 'アカウント編集',
                'user/new' => 'アカウント新規作成',

            'group' => 'グループ',

                'group/{id}' => 'グループ編集',
                'group/new' => 'グループ新規作成',

        'setting' => '設定',

            'resource/cfg' => '一般設定',

                'resource/cfg/{id}' => '設定編集',

        'locale' => '多言語設定',

            'resource/i18n' => 'メッセージ',

                'resource/i18n/{id}' => 'メッセージ編集',

            'resource/i18n/menu' => 'メニュー',

                'resource/i18n/menu/{id}' => 'メニュー文言の編集',

            'resource/i18n/options' => '選択肢',

                'resource/i18n/options/{id}' => '選択肢文言の編集',

            'resource/i18n/model' => 'テーブル',

                'resource/i18n/model/{id}' => 'テーブル文言の編集',

            'resource/i18n/template' => 'メッセージテンプレート',

                'resource/i18n/template/{id}' => 'テンプレート編集',

            'translation' => 'コンテンツ翻訳',

        'messaging' => 'メッセージ管理',

            'mail-log' => 'メール送信履歴',

                'mail-log/{id}' => 'メール送信履歴',

            'sms-log' => 'SMS送信履歴',

                'sms-log/{id}' => 'SMS送信履歴',

            'push-log' => 'プッシュ通知履歴',

                'push-log/{id}' => 'プッシュ通知履歴',

            'telegram-log' => 'Telegram送信履歴',

                'telegram-log/{id}' => 'Telegram送信履歴',

        'geolocation' => '位置情報検索',

];
