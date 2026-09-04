<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

/**
 * @implements Driver<PushLog>
 */
class WebPushDriver implements Driver {

    public function send(MessageLog $log): string {
        $bundle = $log->provider;
        $subscriptions = PushSubscription::query()->where('member_id', $log->member_id)->get();

        if ($subscriptions->isEmpty()) {
            error('push-subscription-not-found');
        }

        $client = $this->client($bundle);
        $payload = strval(json_encode(['title' => $log->title, 'content' => $log->content, 'data' => $log->data]));

        foreach ($subscriptions as $subscription) {
            $client->queueNotification($this->subscription($subscription), $payload);
        }

        $endpoints = [];

        foreach ($client->flush() as $report) {
            $subscription = $subscriptions->firstWhere('endpoint', $report->getEndpoint());

            if ($report->isSuccess()) {
                $endpoints[] = $report->getEndpoint();

                continue;
            }

            if ($report->isSubscriptionExpired() && $subscription !== null) {
                $subscription->delete();
            }
        }

        if ($endpoints === []) {
            error('push-delivery-failed');
        }

        return implode(',', $endpoints);
    }

    private function client(string $bundle): WebPush {
        $http = app()->bound(ClientInterface::class) ? app(ClientInterface::class) : null;

        return new WebPush([
            'VAPID' => [
                'subject' => strval(cfg("{$bundle}.subject")),
                'publicKey' => strval(cfg("{$bundle}.public-key")),
                'privateKey' => strval(cfg("{$bundle}.private-key"))
            ]
        ], [], $http);
    }

    private function subscription(PushSubscription $subscription): Subscription {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'keys' => [
                'p256dh' => $subscription->p256dh,
                'auth' => $subscription->auth
            ]
        ]);
    }

}
