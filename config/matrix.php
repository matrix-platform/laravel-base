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
        'queue' => 'default',
        'channels' => [
            'mail' => ['model' => MailLog::class],
            'sms' => ['model' => SmsLog::class],
        ],
    ],

    'packages' => 'app base',

    'vendor-model' => Vendor::class,

];
