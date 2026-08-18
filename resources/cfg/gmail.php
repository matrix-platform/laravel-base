<?php //>

use MatrixPlatform\Messaging\MailerMailDriver;

return [

    'driver' => MailerMailDriver::class,

    'host' => 'smtp.gmail.com',

    'port' => 587,

    'encryption' => 'tls',

    'username' => '',

    'password' => '',

    'from-address' => '',

    'from-name' => '',

    'interval' => 1,

    'sandbox' => false,

    'sandbox-recipient' => '',

];
