<?php //>

namespace Tests\Feature\Geolocation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Geolocation\Ip2LocationWebServiceDriver;
use Tests\FeatureTestCase;

class Ip2LocationWebServiceDriverTest extends FeatureTestCase {

    public function test_a_successful_response_is_mapped_into_a_location(): void {
        Http::fake(['*' => Http::response([
            'response' => 'OK',
            'country_code' => 'US',
            'country_name' => 'United States of America',
            'region_name' => 'California',
            'city_name' => 'Mountain View',
            'zip_code' => '94043',
            'latitude' => 37.4056,
            'longitude' => -122.0775,
            'time_zone' => '-07:00',
            'isp' => 'Google LLC'
        ])]);

        $location = (new Ip2LocationWebServiceDriver())->locate('8.8.8.8');

        $this->assertNotNull($location);
        $this->assertSame('US', $location->countryCode);
        $this->assertSame('United States of America', $location->countryName);
        $this->assertSame('California', $location->region);
        $this->assertSame('Mountain View', $location->city);
        $this->assertSame('94043', $location->zipCode);
        $this->assertSame(37.4056, $location->latitude);
        $this->assertSame(-122.0775, $location->longitude);
        $this->assertSame('-07:00', $location->timeZone);
        $this->assertSame('Google LLC', $location->isp);
    }

    public function test_the_request_sends_the_configured_key_and_package_for_the_given_ip(): void {
        $this->useCfg('ip2location-webservice', ['api-key' => 'test-key', 'package' => 'WS10']);

        Http::fake(['*' => Http::response(['response' => 'OK', 'country_code' => 'US'])]);

        (new Ip2LocationWebServiceDriver())->locate('8.8.8.8');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('GET', $request->method());
            $this->assertSame('test-key', $request['key']);
            $this->assertSame('8.8.8.8', $request['ip']);
            $this->assertSame('WS10', $request['package']);

            return true;
        });
    }

    public function test_an_error_reported_by_the_provider_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response(['response' => 'INVALID ACCOUNT'])]);

        $this->refuses('geolocation-request-failed', fn () => (new Ip2LocationWebServiceDriver())->locate('8.8.8.8'));
    }

    public function test_a_transport_level_failure_is_refused_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('', 500)]);

        $this->refuses('geolocation-request-failed', fn () => (new Ip2LocationWebServiceDriver())->locate('8.8.8.8'));
    }

    public function test_a_reserved_country_code_from_the_provider_resolves_to_no_location(): void {
        Http::fake(['*' => Http::response(['response' => 'OK', 'country_code' => '-', 'country_name' => '-'])]);

        $this->assertNull((new Ip2LocationWebServiceDriver())->locate('192.168.1.1'));
    }

}
