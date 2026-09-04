<?php //>

namespace MatrixPlatform\Geolocation;

class Location {

    public function __construct(
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly ?string $region,
        public readonly ?string $city,
        public readonly ?string $zipCode,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $timeZone,
        public readonly ?string $isp
    ) {}

}
