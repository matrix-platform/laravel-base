<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Geolocation\Driver;
use MatrixPlatform\Geolocation\Location;

class NotFoundGeolocationDriver implements Driver {

    public function locate(string $ip): ?Location {
        return null;
    }

}
