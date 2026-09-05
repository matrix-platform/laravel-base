<?php //>

namespace MatrixPlatform\Geolocation;

class GeoLocationService {

    public function locate(string $ip): ?Location {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            error('invalid-ip-address');
        }

        $bundle = config()->string('matrix.geolocation-provider');

        return resolve_driver($bundle, Driver::class, 'invalid-geolocation-driver')?->locate($ip);
    }

}
