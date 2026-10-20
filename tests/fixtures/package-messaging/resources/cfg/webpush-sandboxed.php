<?php //>

use MatrixPlatform\Messaging\WebPushDriver;

return [

    'driver' => WebPushDriver::class,

    'subject' => 'mailto:test@example.com',

    'sandbox' => true,

    'sandbox-recipient' => '2',

];
