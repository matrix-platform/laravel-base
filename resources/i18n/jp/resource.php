<?php //>

return [

    'column-name' => '名称',

    'cfg/admin' => '管理者',
    'cfg/admin.captcha-ttl' => 'キャプチャ有効秒数',
    'cfg/admin.login-throttle-max' => 'ログイン試行回数の上限',
    'cfg/admin.login-throttle-window' => 'ログイン制限時間(分)',
    'cfg/admin.password-pattern' => 'パスワードのパターン(正規表現)',
    'cfg/admin.token-idle-minutes' => 'トークンのアイドルタイムアウト(分)',

    'cfg/file' => 'ファイルアップロード',
    'cfg/file.max-size' => '最大サイズ(バイト)',
    'cfg/file.mime-patterns' => '許可するMIMEパターン',

    'cfg/member' => '会員',
    'cfg/member.login-throttle-max' => 'ログイン試行回数の上限',
    'cfg/member.login-throttle-window' => 'ログイン制限時間(分)',
    'cfg/member.password-pattern' => 'パスワードのパターン(正規表現)',
    'cfg/member.token-idle-minutes' => 'トークンのアイドルタイムアウト(分)',

    'cfg/system' => 'システム',
    'cfg/system.date-format' => '日付形式',
    'cfg/system.datetime-format' => '日付時刻形式',

    'cfg/vendor' => 'ベンダー',
    'cfg/vendor.login-throttle-max' => 'ログイン試行回数の上限',
    'cfg/vendor.login-throttle-window' => 'ログイン制限時間(分)',
    'cfg/vendor.password-pattern' => 'パスワードのパターン(正規表現)',
    'cfg/vendor.token-idle-minutes' => 'トークンのアイドルタイムアウト(分)',

    'cfg/gmail' => 'Gmail SMTP',
    'cfg/gmail.driver' => 'ドライバー',
    'cfg/gmail.host' => 'SMTPホスト',
    'cfg/gmail.port' => 'SMTPポート',
    'cfg/gmail.encryption' => '暗号化方式',
    'cfg/gmail.username' => 'アカウント',
    'cfg/gmail.password' => 'パスワード',
    'cfg/gmail.from-address' => '送信元メールアドレス',
    'cfg/gmail.from-name' => '送信者名',
    'cfg/gmail.interval' => '送信間隔(秒)',
    'cfg/gmail.sandbox' => 'サンドボックスモード',
    'cfg/gmail.sandbox-recipient' => 'サンドボックス受信者',

    'cfg/mitake' => 'Mitake SMS',
    'cfg/mitake.driver' => 'ドライバー',
    'cfg/mitake.endpoint' => 'APIエンドポイント',
    'cfg/mitake.username' => 'アカウント',
    'cfg/mitake.password' => 'パスワード',
    'cfg/mitake.accepted-status' => '成功とみなすステータスコード',
    'cfg/mitake.interval' => '送信間隔(秒)',
    'cfg/mitake.sandbox' => 'サンドボックスモード',
    'cfg/mitake.sandbox-recipient' => 'サンドボックス受信者',

];
