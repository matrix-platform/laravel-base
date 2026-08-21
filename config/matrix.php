<?php //>

use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\Vendor;

return [

    'admin-api-prefix' => 'admin',

    'admin-menus' => 'base',

    'api-prefix' => 'api',

    'file-private-disk' => 'local',

    'file-public-disk' => 'public',

    'locales' => 'tw en',

    'member-model' => Member::class,

    'messaging' => [
        'channels' => [
            'mail' => ['model' => MailLog::class, 'queue' => 'messaging-mail'],
            'sms' => ['model' => SmsLog::class, 'queue' => 'messaging-sms'],
        ],
    ],

    'packages' => 'app base',

    'resource-cfg' => [],

    'resource-i18n' => [],

    'resource-i18n-menu' => [],

    'resource-i18n-model' => [],

    'resource-i18n-options' => [],

    'resource-i18n-template' => [],

    'vendor-model' => Vendor::class,

];
