<?php //>

use MatrixPlatform\Messaging\MailerMailDriver;

return [

    'driver' => MailerMailDriver::class,

    'host' => 'smtp.relay.example',

    'port' => 2525,

    'encryption' => 'ssl',

    'from-address' => 'relay@example.com',

];
