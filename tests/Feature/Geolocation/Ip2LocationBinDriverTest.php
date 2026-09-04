<?php //>

namespace Tests\Feature\Geolocation;

use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Geolocation\Ip2LocationBinDriver;
use Tests\FeatureTestCase;

class Ip2LocationBinDriverTest extends FeatureTestCase {

    private const FIXTURE = __DIR__ . '/../../fixtures/geolocation/IP2LOCATION-LITE-DB1.BIN';

    protected function setUp(): void {
        parent::setUp();

        $this->useCfg('ip2location-bin', ['bin-path' => 'ip2location-test.bin']);

        Storage::fake('local');

        Storage::disk('local')->put('ip2location-test.bin', strval(file_get_contents(self::FIXTURE)));
    }

    public function test_a_known_public_ip_resolves_its_country(): void {
        $location = (new Ip2LocationBinDriver())->locate('8.8.8.8');

        $this->assertNotNull($location);
        $this->assertSame('US', $location->countryCode);
        $this->assertSame('United States of America', $location->countryName);
    }

    public function test_a_field_the_loaded_database_tier_does_not_carry_is_null_not_the_vendor_placeholder_text(): void {
        $location = (new Ip2LocationBinDriver())->locate('8.8.8.8');

        $this->assertNotNull($location);
        $this->assertNull($location->region);
        $this->assertNull($location->city);
        $this->assertNull($location->zipCode);
        $this->assertNull($location->latitude);
        $this->assertNull($location->longitude);
        $this->assertNull($location->timeZone);
        $this->assertNull($location->isp);
    }

    public function test_a_private_ip_address_resolves_to_no_location_rather_than_the_vendors_reserved_marker(): void {
        $this->assertNull((new Ip2LocationBinDriver())->locate('192.168.1.1'));
    }

    public function test_an_ipv6_address_looked_up_against_an_ipv4_only_database_resolves_to_no_location(): void {
        $this->assertNull((new Ip2LocationBinDriver())->locate('2001:4860:4860::8888'));
    }

    public function test_a_missing_database_file_is_refused_with_a_clear_error(): void {
        Storage::disk('local')->delete('ip2location-test.bin');

        $this->refuses('geolocation-database-not-found', fn () => (new Ip2LocationBinDriver())->locate('8.8.8.8'));
    }

}
