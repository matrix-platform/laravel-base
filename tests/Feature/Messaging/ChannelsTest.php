<?php //>

namespace Tests\Feature\Messaging;

use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Services\Messaging\MailService;
use Tests\FeatureTestCase;
use Tests\Stubs\Gizmo;

class ChannelsTest extends FeatureTestCase {

    private function channels(): Channels {
        return app(Channels::class);
    }

    public function test_a_configured_channel_resolves_to_its_log_model(): void {
        $channel = $this->channels()->get('mail');

        $this->assertSame('mail', $channel->name);
        $this->assertSame(MailLog::class, $channel->model);
    }

    public function test_every_configured_channel_is_listed(): void {
        $this->assertSame(['mail', 'sms'], $this->channels()->names());
    }

    public function test_an_unknown_channel_is_refused(): void {
        $this->refuses('unknown-message-channel', fn () => $this->channels()->get('carrier-pigeon'));
    }

    public function test_a_model_that_is_not_a_message_log_is_refused(): void {
        config()->set('matrix.messaging.channels.mail.model', Gizmo::class);

        $this->refuses('invalid-message-channel', fn () => $this->channels()->get('mail'));
    }

    public function test_a_broken_channel_does_not_disable_the_others(): void {
        config()->set('matrix.messaging.channels.pigeon', ['model' => Gizmo::class]);

        $this->useMessagingFixtures();

        $log = app(MailService::class)->send('alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->assertSame('alice@example.com', $log->receiver);
        $this->refuses('invalid-message-channel', fn () => $this->channels()->get('pigeon'));
    }

    public function test_the_registry_reflects_a_configuration_change_without_being_forgotten(): void {
        $channels = $this->channels();

        $this->assertSame(MailLog::class, $channels->get('mail')->model);

        config()->set('matrix.messaging.channels.mail.model', SmsLog::class);

        $this->assertSame(SmsLog::class, $channels->get('mail')->model);
    }

}
