<?php //>

use MatrixPlatform\Messaging\MitakeSmsDriver;

return [

    'driver' => MitakeSmsDriver::class,

    'endpoint' => 'https://smsapi.mitake.com.tw/',

    'username' => '',

    'password' => '',

    'accepted-status' => ['0', '1', '2', '4'],

    'interval' => 1,

    'sandbox' => false,

    'sandbox-recipient' => '',

];
