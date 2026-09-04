<?php //>

namespace MatrixPlatform\Geolocation;

interface Driver {

    public const RESERVED_COUNTRY_CODE = '-';

    public function locate(string $ip): ?Location;

}
