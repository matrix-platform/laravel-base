<?php //>

namespace MatrixPlatform\Geolocation;

use Illuminate\Support\Facades\Http;

class Ip2LocationWebServiceDriver implements Driver {

    public function locate(string $ip): ?Location {
        $response = Http::get(strval(cfg('ip2location-webservice.endpoint')), [
            'key' => strval(cfg('ip2location-webservice.api-key')),
            'ip' => $ip,
            'package' => strval(cfg('ip2location-webservice.package'))
        ]);

        $body = $response->json();

        if ($response->failed() || !is_array($body) || array_get_value($body, 'response') !== 'OK') {
            error('geolocation-request-failed');
        }

        if (array_get_value($body, 'country_code') === self::RESERVED_COUNTRY_CODE) {
            return null;
        }

        return new Location(
            countryCode: $this->field($body, 'country_code'),
            countryName: $this->field($body, 'country_name'),
            region: $this->field($body, 'region_name'),
            city: $this->field($body, 'city_name'),
            zipCode: $this->field($body, 'zip_code'),
            latitude: $this->coordinate($body, 'latitude'),
            longitude: $this->coordinate($body, 'longitude'),
            timeZone: $this->field($body, 'time_zone'),
            isp: $this->field($body, 'isp')
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function coordinate(array $body, string $key): ?float {
        $value = array_get_value($body, $key);

        return $value === null ? null : floatval($value);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function field(array $body, string $key): ?string {
        $value = array_get_value($body, $key);

        return $value === null ? null : strval($value);
    }

}
