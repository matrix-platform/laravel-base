<?php //>

use MatrixPlatform\Messaging\MailerMailDriver;

return [

    'driver' => MailerMailDriver::class,

    'sandbox' => false,

    'sandbox-recipient' => 'never-used@example.com',

];
