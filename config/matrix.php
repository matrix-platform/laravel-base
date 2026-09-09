<?php //>

use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\Vendor;

return [

    'admin-api-prefix' => 'admin',

    'admin-menus' => 'base',

    'api-prefix' => 'api',

    'date-format' => 'Y-m-d',

    'datetime-format' => 'Y-m-d H:i:s',

    'drive-disk' => 'local',

    'file-private-disk' => 'local',

    'file-public-disk' => 'public',

    'geolocation-provider' => 'ip2location-bin',

    'locales' => 'tw en',

    'member-model' => Member::class,

    'messaging' => [
        'channels' => [
            'mail' => ['model' => MailLog::class, 'queue' => 'messaging-mail'],
            'push' => ['model' => PushLog::class, 'queue' => 'messaging-push'],
            'sms' => ['model' => SmsLog::class, 'queue' => 'messaging-sms'],
            'telegram' => ['model' => TelegramLog::class, 'queue' => 'messaging-telegram'],
        ],
    ],

    'packages' => 'app base',

    'passkey-rp-id' => null,

    'resource-cfg' => [],

    'resource-i18n' => [],

    'resource-i18n-menu' => [],

    'resource-i18n-model' => [],

    'resource-i18n-options' => [],

    'resource-i18n-template' => [],

    'thumbnail-quality' => 80,

    'thumbnail-sizes' => [
        'icon' => 64,
        'thumb' => 256,
    ],

    'translation-provider' => 'google-translate',

    'vendor-api-prefix' => 'vendor',

    'vendor-model' => Vendor::class,

];
