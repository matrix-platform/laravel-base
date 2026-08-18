<?php //>

use MatrixPlatform\Messaging\MitakeSmsDriver;

return [

    'driver' => MitakeSmsDriver::class,

    'endpoint' => 'https://sms.example.test',

    'username' => 'mitake-account',

    'password' => 'mitake-secret',

];
