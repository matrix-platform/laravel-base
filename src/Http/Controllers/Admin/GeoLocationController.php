<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Geolocation\GeoLocationService;
use MatrixPlatform\Geolocation\Location;
use MatrixPlatform\Http\Controllers\BaseController;

class GeoLocationController extends BaseController {

    public function __construct(private GeoLocationService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action('')]
    public function locate(Request $request): array {
        $request->validate(['ip' => ['required', 'ip']]);

        $location = $this->service->locate($request->string('ip')->value());

        return $location === null ? ['found' => false] : ['found' => true, 'location' => $this->present($location)];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Location $location): array {
        return [
            'country_code' => $location->countryCode,
            'country_name' => $location->countryName,
            'region' => $location->region,
            'city' => $location->city,
            'zip_code' => $location->zipCode,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'time_zone' => $location->timeZone,
            'isp' => $location->isp
        ];
    }

}
