<?php //>

return [

    'column-name' => '名稱',
    'column-overrides' => '已覆寫數',

    'cfg/admin' => '帳號',
    'cfg/admin.captcha-ttl' => '驗證碼有效秒數',
    'cfg/admin.login-throttle-max' => '登入嘗試次數上限',
    'cfg/admin.login-throttle-window' => '登入限制時間(分鐘)',
    'cfg/admin.password-pattern' => '密碼規則(正規表達式)',
    'cfg/admin.token-idle-minutes' => '憑證閒置逾時(分鐘)',

    'cfg/file' => '檔案上傳',
    'cfg/file.max-size' => '檔案大小上限(位元組)',
    'cfg/file.mime-patterns' => '允許的 MIME 樣式',

    'cfg/member' => '會員',
    'cfg/member.login-throttle-max' => '登入嘗試次數上限',
    'cfg/member.login-throttle-window' => '登入限制時間(分鐘)',
    'cfg/member.token-idle-minutes' => '憑證閒置逾時(分鐘)',

    'cfg/system' => '系統',
    'cfg/system.date-format' => '日期格式',
    'cfg/system.datetime-format' => '日期時間格式',

    'cfg/vendor' => '廠商',
    'cfg/vendor.login-throttle-max' => '登入嘗試次數上限',
    'cfg/vendor.login-throttle-window' => '登入限制時間(分鐘)',
    'cfg/vendor.token-idle-minutes' => '憑證閒置逾時(分鐘)',

    'cfg/gmail' => 'Gmail SMTP',
    'cfg/gmail.driver' => '傳送器',
    'cfg/gmail.host' => 'SMTP 主機',
    'cfg/gmail.port' => 'SMTP 連接埠',
    'cfg/gmail.encryption' => '加密方式',
    'cfg/gmail.username' => '帳號',
    'cfg/gmail.password' => '密碼',
    'cfg/gmail.from-address' => '寄件者信箱',
    'cfg/gmail.from-name' => '寄件者名稱',
    'cfg/gmail.interval' => '發送間隔秒數',
    'cfg/gmail.sandbox' => '沙箱模式',
    'cfg/gmail.sandbox-recipient' => '沙箱收件者',

    'cfg/mitake' => '三竹簡訊',
    'cfg/mitake.driver' => '傳送器',
    'cfg/mitake.endpoint' => 'API 端點',
    'cfg/mitake.username' => '帳號',
    'cfg/mitake.password' => '密碼',
    'cfg/mitake.accepted-status' => '視為成功的狀態碼',
    'cfg/mitake.interval' => '發送間隔秒數',
    'cfg/mitake.sandbox' => '沙箱模式',
    'cfg/mitake.sandbox-recipient' => '沙箱收件者',

];
