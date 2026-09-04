<?php //>

use MatrixPlatform\Geolocation\Ip2LocationBinDriver;

return [

    'driver' => Ip2LocationBinDriver::class,

    'bin-path' => 'ip2location.bin',

    'download-token' => '',

    'db-code' => 'DB11LITEBIN',

];
