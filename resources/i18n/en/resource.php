<?php //>

return [

    'column-name' => 'Name',
    'column-overrides' => 'Overrides',

    'cfg/admin' => 'User',
    'cfg/admin.captcha-ttl' => 'Captcha lifetime (seconds)',
    'cfg/admin.login-throttle-max' => 'Login attempt limit',
    'cfg/admin.login-throttle-window' => 'Login throttle window (minutes)',
    'cfg/admin.password-pattern' => 'Password pattern',
    'cfg/admin.token-idle-minutes' => 'Token idle timeout (minutes)',

    'cfg/file' => 'File uploads',
    'cfg/file.max-size' => 'Maximum size (bytes)',
    'cfg/file.mime-patterns' => 'Accepted MIME patterns',

    'cfg/member' => 'Member',
    'cfg/member.login-throttle-max' => 'Login attempt limit',
    'cfg/member.login-throttle-window' => 'Login throttle window (minutes)',
    'cfg/member.token-idle-minutes' => 'Token idle timeout (minutes)',

    'cfg/system' => 'System',
    'cfg/system.date-format' => 'Date format',
    'cfg/system.datetime-format' => 'Date and time format',

    'cfg/vendor' => 'Vendor',
    'cfg/vendor.login-throttle-max' => 'Login attempt limit',
    'cfg/vendor.login-throttle-window' => 'Login throttle window (minutes)',
    'cfg/vendor.token-idle-minutes' => 'Token idle timeout (minutes)',

    'cfg/gmail' => 'Gmail SMTP',
    'cfg/gmail.driver' => 'Driver',
    'cfg/gmail.host' => 'SMTP host',
    'cfg/gmail.port' => 'SMTP port',
    'cfg/gmail.encryption' => 'Encryption',
    'cfg/gmail.username' => 'Account',
    'cfg/gmail.password' => 'Password',
    'cfg/gmail.from-address' => 'Sender address',
    'cfg/gmail.from-name' => 'Sender name',
    'cfg/gmail.interval' => 'Send interval (seconds)',
    'cfg/gmail.sandbox' => 'Sandbox mode',
    'cfg/gmail.sandbox-recipient' => 'Sandbox recipient',

    'cfg/mitake' => 'Mitake SMS',
    'cfg/mitake.driver' => 'Driver',
    'cfg/mitake.endpoint' => 'API endpoint',
    'cfg/mitake.username' => 'Account',
    'cfg/mitake.password' => 'Password',
    'cfg/mitake.accepted-status' => 'Accepted status codes',
    'cfg/mitake.interval' => 'Send interval (seconds)',
    'cfg/mitake.sandbox' => 'Sandbox mode',
    'cfg/mitake.sandbox-recipient' => 'Sandbox recipient',

];
