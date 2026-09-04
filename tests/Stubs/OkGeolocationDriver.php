<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Geolocation\Driver;
use MatrixPlatform\Geolocation\Location;

class OkGeolocationDriver implements Driver {

    public static ?string $requested = null;

    public function locate(string $ip): ?Location {
        self::$requested = $ip;

        return new Location(
            countryCode: 'US',
            countryName: 'United States of America',
            region: null,
            city: null,
            zipCode: null,
            latitude: null,
            longitude: null,
            timeZone: null,
            isp: null
        );
    }

}
