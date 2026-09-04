<?php //>

namespace MatrixPlatform\Geolocation;

class GeoLocationService {

    public function locate(string $ip): ?Location {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            error('invalid-ip-address');
        }

        $bundle = config()->string('matrix.geolocation-provider');
        $class = cfg("{$bundle}.driver");

        if ($class === null) {
            return null;
        }

        if (!is_string($class) || !is_a($class, Driver::class, true)) {
            error('invalid-geolocation-driver');
        }

        return app($class)->locate($ip);
    }

}
