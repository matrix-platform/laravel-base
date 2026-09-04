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

        $this->useCfg('webpush', [
            'subject' => 'mailto:test@example.com',
            'public-key' => $keys['publicKey'],
            'private-key' => $keys['privateKey']
        ]);
    }

    private function log(): PushLog {
        $log = new PushLog();

        $log->provider = 'webpush';
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

    private function subscription(int $memberId = 1): PushSubscription {
        $subscription = new PushSubscription();

        $subscription->member_id = $memberId;
        $subscription->endpoint = 'https://push.example.test/' . uniqid();
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

    public function test_a_receiver_with_no_subscriptions_is_refused(): void {
        $this->refuses('push-subscription-not-found', fn () => (new WebPushDriver())->send($this->log()));
    }

    public function test_a_failure_that_is_not_an_expiry_does_not_delete_the_subscription(): void {
        $subscription = $this->subscription();

        $this->mockClient([new Response(500)]);

        $this->refuses('push-delivery-failed', fn () => (new WebPushDriver())->send($this->log()));

        $this->assertNotNull($subscription->fresh());
    }

}
