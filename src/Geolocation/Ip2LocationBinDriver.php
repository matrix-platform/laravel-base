<?php //>

namespace MatrixPlatform\Geolocation;

use Illuminate\Support\Facades\Storage;
use IP2Location\Database;

class Ip2LocationBinDriver implements Driver {

    public function locate(string $ip): ?Location {
        $database = new Database($this->path(), Database::FILE_IO);
        $records = $database->lookup($ip, Database::ALL);

        if (!is_array($records) || array_get_value($records, 'countryCode') === self::RESERVED_COUNTRY_CODE) {
            return null;
        }

        return new Location(
            countryCode: $this->field($records, 'countryCode'),
            countryName: $this->field($records, 'countryName'),
            region: $this->field($records, 'regionName'),
            city: $this->field($records, 'cityName'),
            zipCode: $this->field($records, 'zipCode'),
            latitude: $this->coordinate($records, 'latitude'),
            longitude: $this->coordinate($records, 'longitude'),
            timeZone: $this->field($records, 'timeZone'),
            isp: $this->field($records, 'isp')
        );
    }

    /**
     * @param array<string, mixed> $records
     */
    private function coordinate(array $records, string $key): ?float {
        $value = array_get_value($records, $key);

        return $this->supported($value) ? floatval($value) : null;
    }

    /**
     * @param array<string, mixed> $records
     */
    private function field(array $records, string $key): ?string {
        $value = array_get_value($records, $key);

        return $this->supported($value) ? strval($value) : null;
    }

    private function path(): string {
        $disk = config()->string('matrix.file-private-disk');
        $relative = strval(cfg('ip2location-bin.bin-path'));

        if (!Storage::disk($disk)->exists($relative)) {
            error('geolocation-database-not-found');
        }

        return Storage::disk($disk)->path($relative);
    }

    private function supported(mixed $value): bool {
        return $value !== null && $value !== Database::FIELD_NOT_SUPPORTED && $value !== Database::FIELD_NOT_KNOWN;
    }

}
