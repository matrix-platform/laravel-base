<?php //>

use MatrixPlatform\Messaging\MitakeSmsDriver;

return [

    'driver' => MitakeSmsDriver::class,

    'endpoint' => 'https://sms.example.test',

    'accepted-status' => ['1'],

    'sandbox' => true,

    'sandbox-recipient' => '0900000000',

];
