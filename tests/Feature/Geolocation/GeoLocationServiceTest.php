<?php //>

namespace Tests\Feature\Geolocation;

use MatrixPlatform\Geolocation\GeoLocationService;
use Tests\FeatureTestCase;
use Tests\Stubs\OkGeolocationDriver;

class GeoLocationServiceTest extends FeatureTestCase {

    private function service(): GeoLocationService {
        return app(GeoLocationService::class);
    }

    public function test_an_invalid_ip_format_is_refused_before_any_driver_runs(): void {
        $this->refuses('invalid-ip-address', fn () => $this->service()->locate('not-an-ip'));
    }

    public function test_a_provider_with_no_driver_configured_returns_no_location_instead_of_failing(): void {
        config()->set('matrix.geolocation-provider', 'does-not-exist');

        $this->assertNull($this->service()->locate('8.8.8.8'));
    }

    public function test_a_provider_naming_a_class_that_is_not_a_driver_is_refused(): void {
        $this->useGeolocationFixtures();

        config()->set('matrix.geolocation-provider', 'broken');

        $this->refuses('invalid-geolocation-driver', fn () => $this->service()->locate('8.8.8.8'));
    }

    public function test_the_configured_providers_driver_receives_the_ip_and_its_result_is_returned(): void {
        $this->useGeolocationFixtures();

        config()->set('matrix.geolocation-provider', 'stub');

        $location = $this->service()->locate('8.8.8.8');

        $this->assertSame('8.8.8.8', OkGeolocationDriver::$requested);
        $this->assertSame('US', $location?->countryCode);
    }

}
