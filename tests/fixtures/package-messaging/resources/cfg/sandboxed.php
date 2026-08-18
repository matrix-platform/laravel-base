<?php //>

use MatrixPlatform\Messaging\MailerMailDriver;

return [

    'driver' => MailerMailDriver::class,

    'host' => 'smtp.sandbox.example',

    'sandbox' => true,

    'sandbox-recipient' => 'sink@example.com',

];
