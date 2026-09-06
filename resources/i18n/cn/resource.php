<?php //>

return [

    'column-name' => '名称',

    'cfg/admin' => '账号',
    'cfg/admin.captcha-ttl' => '验证码有效秒数',
    'cfg/admin.login-throttle-max' => '登录尝试次数上限',
    'cfg/admin.login-throttle-window' => '登录限制时间(分钟)',
    'cfg/admin.password-pattern' => '密码规则(正则表达式)',
    'cfg/admin.token-idle-minutes' => '凭证闲置超时(分钟)',

    'cfg/file' => '文件上传',
    'cfg/file.max-size' => '文件大小上限(字节)',
    'cfg/file.mime-patterns' => '允许的 MIME 类型',

    'cfg/member' => '会员',
    'cfg/member.login-throttle-max' => '登录尝试次数上限',
    'cfg/member.login-throttle-window' => '登录限制时间(分钟)',
    'cfg/member.password-pattern' => '密码规则(正则表达式)',
    'cfg/member.token-idle-minutes' => '凭证闲置超时(分钟)',

    'cfg/system' => '系统',
    'cfg/system.date-format' => '日期格式',
    'cfg/system.datetime-format' => '日期时间格式',

    'cfg/vendor' => '厂商',
    'cfg/vendor.login-throttle-max' => '登录尝试次数上限',
    'cfg/vendor.login-throttle-window' => '登录限制时间(分钟)',
    'cfg/vendor.password-pattern' => '密码规则(正则表达式)',
    'cfg/vendor.token-idle-minutes' => '凭证闲置超时(分钟)',

    'cfg/gmail' => 'Gmail SMTP',
    'cfg/gmail.driver' => '发送器',
    'cfg/gmail.host' => 'SMTP 主机',
    'cfg/gmail.port' => 'SMTP 端口',
    'cfg/gmail.encryption' => '加密方式',
    'cfg/gmail.username' => '账号',
    'cfg/gmail.password' => '密码',
    'cfg/gmail.from-address' => '发件人邮箱',
    'cfg/gmail.from-name' => '发件人名称',
    'cfg/gmail.interval' => '发送间隔(秒)',
    'cfg/gmail.sandbox' => '沙箱模式',
    'cfg/gmail.sandbox-recipient' => '沙箱收件人',

    'cfg/mitake' => 'Mitake SMS',
    'cfg/mitake.driver' => '发送器',
    'cfg/mitake.endpoint' => 'API 端点',
    'cfg/mitake.username' => '账号',
    'cfg/mitake.password' => '密码',
    'cfg/mitake.accepted-status' => '视为成功的状态码',
    'cfg/mitake.interval' => '发送间隔(秒)',
    'cfg/mitake.sandbox' => '沙箱模式',
    'cfg/mitake.sandbox-recipient' => '沙箱收件人',

];
