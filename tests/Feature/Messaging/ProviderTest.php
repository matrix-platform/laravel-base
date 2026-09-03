<?php //>

namespace Tests\Feature\Messaging;

use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Messaging\MailerMailDriver;
use MatrixPlatform\Messaging\Provider;
use Tests\FeatureTestCase;
use Tests\Stubs\OkDriver;

class ProviderTest extends FeatureTestCase {

    private function provider(string $name): Provider {
        return app(Channels::class)->get('mail')->provider($name);
    }

    public function test_a_shipped_provider_names_the_driver_that_carries_it(): void {
        $provider = $this->provider('gmail');

        $this->assertSame('gmail', $provider->name);
        $this->assertInstanceOf(Driver::class, $provider->driver());
    }

    public function test_two_providers_on_one_channel_can_use_different_drivers(): void {
        $this->useMessagingFixtures();

        $this->assertInstanceOf(OkDriver::class, $this->provider('stub')->driver());
        $this->assertInstanceOf(MailerMailDriver::class, $this->provider('relay')->driver());
    }

    public function test_a_provider_without_a_configuration_bundle_is_refused(): void {
        $this->refuses('invalid-message-provider', fn () => $this->provider('does-not-exist'));
    }

    public function test_an_empty_provider_name_is_refused(): void {
        $this->refuses('invalid-message-provider', fn () => $this->provider(''));
    }

    public function test_a_provider_that_names_no_driver_yet_is_legal(): void {
        $this->useMessagingFixtures();

        $this->assertNull($this->provider('bare')->driver());
    }

    public function test_a_provider_naming_a_class_that_is_not_a_driver_is_refused(): void {
        $this->useMessagingFixtures();

        $this->refuses('invalid-message-driver', fn () => $this->provider('broken')->driver());
    }

}
