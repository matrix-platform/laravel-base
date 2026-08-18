<?php //>

namespace Tests\Feature\Messaging;

use MatrixPlatform\Messaging\Sandbox;
use Tests\FeatureTestCase;

class SandboxTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMessagingFixtures();
    }

    public function test_a_provider_is_not_sandboxed_by_default(): void {
        $this->assertNull(Sandbox::recipient('gmail'));
        $this->assertNull(Sandbox::recipient('stub'));
    }

    public function test_a_sandboxed_provider_reports_its_own_sink(): void {
        $this->assertSame('sink@example.com', Sandbox::recipient('sandboxed'));
    }

    public function test_one_provider_being_sandboxed_does_not_sandbox_the_others(): void {
        $this->assertSame('sink@example.com', Sandbox::recipient('sandboxed'));
        $this->assertNull(Sandbox::recipient('unsandboxed'));
    }

    public function test_a_sandboxed_provider_without_a_sink_is_refused(): void {
        $this->refuses('invalid-message-receiver', fn () => Sandbox::recipient('blind'));
    }

    public function test_a_channel_the_package_does_not_ship_follows_the_same_convention(): void {
        $this->assertNull(Sandbox::recipient('twilio'));
    }

    public function test_the_response_records_where_the_message_actually_went(): void {
        $this->assertSame('real-id', Sandbox::response(null, 'real-id'));
        $this->assertSame('sandbox: sink@example.com real-id', Sandbox::response('sink@example.com', 'real-id'));
    }

    public function test_the_response_stays_readable_when_the_transport_reports_no_identifier(): void {
        $this->assertSame('', Sandbox::response(null, ''));
        $this->assertSame('sandbox: 0900000000', Sandbox::response('0900000000', ''));
    }

}
