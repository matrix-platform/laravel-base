<?php //>

namespace Tests\Feature\Messaging;

use Base64Url\Base64Url;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\WebPushDriver;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Models\PushSubscription;
use Minishlink\WebPush\VAPID;
use Psr\Http\Client\ClientInterface;
use Tests\FeatureTestCase;

class WebPushDriverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $keys = VAPID::createVapidKeys();

        config(['matrix.webpush-hosts' => ['push.example.test']]);

        $this->useCfg('webpush', [
            'subject' => 'mailto:test@example.com',
            'public-key' => $keys['publicKey'],
            'private-key' => $keys['privateKey']
        ]);
    }

    private function log(string $provider = 'webpush'): PushLog {
        $log = new PushLog();

        $log->provider = $provider;
        $log->member_id = 1;
        $log->title = 'Notice';
        $log->content = 'Body';
        $log->schedule_time = now();
        $log->status = MessageStatus::Scheduled;
        $log->locale = 'en';

        $log->save();

        return $log;
    }

    /**
     * @param list<Response> $responses
     */
    private function mockClient(array $responses): void {
        app()->instance(ClientInterface::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function useSandbox(array $overrides = []): void {
        $keys = VAPID::createVapidKeys();

        $this->useMessagingFixtures();
        $this->useCfg('webpush-sandboxed', array_merge(['public-key' => $keys['publicKey'], 'private-key' => $keys['privateKey']], $overrides));
    }

    private function subscription(int $memberId = 1, ?string $endpoint = null): PushSubscription {
        $subscription = new PushSubscription();

        $subscription->member_id = $memberId;
        $subscription->endpoint = $endpoint === null ? 'https://push.example.test/' . uniqid() : $endpoint;
        $subscription->p256dh = VAPID::createVapidKeys()['publicKey'];
        $subscription->auth = Base64Url::encode(random_bytes(16));

        $subscription->save();

        return $subscription;
    }

    public function test_a_successful_delivery_returns_the_endpoints_and_keeps_the_subscriptions(): void {
        $first = $this->subscription();
        $second = $this->subscription();

        $this->mockClient([new Response(201), new Response(201)]);

        $response = (new WebPushDriver())->send($this->log());

        $this->assertStringContainsString($first->endpoint, $response);
        $this->assertStringContainsString($second->endpoint, $response);
        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());
    }

    public function test_an_expired_subscription_is_deleted_while_other_deliveries_still_succeed(): void {
        $active = $this->subscription();
        $expired = $this->subscription();

        $this->mockClient([new Response(201), new Response(410)]);

        (new WebPushDriver())->send($this->log());

        $this->assertNotNull($active->fresh());
        $this->assertNull($expired->fresh());
    }

    public function test_an_endpoint_outside_the_push_services_is_skipped_and_kept(): void {
        $allowed = $this->subscription();
        $blocked = $this->subscription(endpoint: 'https://10.0.0.5/hook');
        $handler = new MockHandler([new Response(201), new Response(201)]);

        app()->instance(ClientInterface::class, new Client(['handler' => HandlerStack::create($handler)]));

        $response = (new WebPushDriver())->send($this->log());

        $this->assertSame($allowed->endpoint, $response);
        $this->assertSame(1, $handler->count());
        $this->assertNotNull($blocked->fresh());
    }

    public function test_a_receiver_with_only_unsupported_endpoints_is_refused(): void {
        $this->subscription(endpoint: 'https://10.0.0.5/hook');

        $this->refuses('push-subscription-not-found', fn () => (new WebPushDriver())->send($this->log()));
    }

    public function test_a_receiver_with_no_subscriptions_is_refused(): void {
        $this->refuses('push-subscription-not-found', fn () => (new WebPushDriver())->send($this->log()));
    }

    public function test_a_sandboxed_provider_sends_to_the_sink_member_instead(): void {
        $real = $this->subscription();
        $sink = $this->subscription(2);

        $this->useSandbox();
        $this->mockClient([new Response(201)]);

        $response = (new WebPushDriver())->send($this->log('webpush-sandboxed'));

        $this->assertSame("sandbox: 2 {$sink->endpoint}", $response);
        $this->assertStringNotContainsString($real->endpoint, $response);
    }

    public function test_a_sandboxed_expiry_does_not_delete_the_sink_subscription(): void {
        $sink = $this->subscription(2);

        $this->useSandbox();
        $this->mockClient([new Response(410)]);

        $this->refuses('push-delivery-failed', fn () => (new WebPushDriver())->send($this->log('webpush-sandboxed')));

        $this->assertNotNull($sink->fresh());
    }

    public function test_a_sandbox_without_a_recipient_is_refused(): void {
        $this->subscription();

        $this->useSandbox(['sandbox-recipient' => '']);

        $this->refuses('invalid-message-receiver', fn () => (new WebPushDriver())->send($this->log('webpush-sandboxed')));
    }

    public function test_a_failure_that_is_not_an_expiry_does_not_delete_the_subscription(): void {
        $subscription = $this->subscription();

        $this->mockClient([new Response(500)]);

        $this->refuses('push-delivery-failed', fn () => (new WebPushDriver())->send($this->log()));

        $this->assertNotNull($subscription->fresh());
    }

}
