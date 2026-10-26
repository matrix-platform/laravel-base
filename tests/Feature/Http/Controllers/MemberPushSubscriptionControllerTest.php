<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\PushSubscription;
use Tests\Factories\MemberFactory;
use Tests\FeatureTestCase;

class MemberPushSubscriptionControllerTest extends FeatureTestCase {

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-secret']
        ], $overrides);
    }

    private function token(int $id = 1): string {
        return MemberFactory::new()->createOne(['id' => $id])->createToken();
    }

    public function test_subscribing_without_a_token_is_refused(): void {
        $this->postJson('api/member/push/subscribe', $this->payload())
            ->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_subscribing_creates_a_subscription_for_the_current_member(): void {
        $this->withToken($this->token(5))
            ->postJson('api/member/push/subscribe', $this->payload())
            ->assertJson(['success' => true]);

        $subscription = PushSubscription::query()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/device-1')->firstOrFail();

        $this->assertSame(5, $subscription->member_id);
        $this->assertSame('p256dh-key', $subscription->p256dh);
        $this->assertSame('auth-secret', $subscription->auth);
    }

    public function test_subscribing_again_with_the_same_endpoint_updates_rather_than_duplicates(): void {
        $token = $this->token(5);

        $this->withToken($token)->postJson('api/member/push/subscribe', $this->payload());
        $this->withToken($token)->postJson('api/member/push/subscribe', $this->payload(['keys' => ['p256dh' => 'new-key', 'auth' => 'new-secret']]));

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('new-key', PushSubscription::query()->firstOrFail()->p256dh);
    }

    public function test_subscribing_without_required_fields_is_a_validation_failure(): void {
        $this->withToken($this->token())
            ->postJson('api/member/push/subscribe', [])
            ->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
    }

    public function test_an_endpoint_that_is_not_https_is_refused(): void {
        $this->withToken($this->token())
            ->postJson('api/member/push/subscribe', $this->payload(['endpoint' => 'http://fcm.googleapis.com/fcm/send/device-1']))
            ->assertJsonPath('fields.endpoint', ['unsupported-push-endpoint']);

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_an_endpoint_outside_the_push_services_is_refused(): void {
        $token = $this->token();

        foreach (['https://169.254.169.254/latest/meta-data', 'https://internal.example.test/hook', 'https://fcm.googleapis.com.example.test/send'] as $endpoint) {
            $this->withToken($token)
                ->postJson('api/member/push/subscribe', $this->payload(['endpoint' => $endpoint]))
                ->assertJsonPath('fields.endpoint', ['unsupported-push-endpoint']);
        }

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_an_endpoint_on_a_non_default_port_is_refused(): void {
        $this->withToken($this->token(5))
            ->postJson('api/member/push/subscribe', $this->payload(['endpoint' => 'https://fcm.googleapis.com:8443/fcm/send/device-1']))
            ->assertJsonPath('fields.endpoint', ['unsupported-push-endpoint']);
    }

    public function test_a_subdomain_of_a_wildcard_push_service_is_accepted(): void {
        $this->withToken($this->token())
            ->postJson('api/member/push/subscribe', $this->payload(['endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/abc']))
            ->assertJson(['success' => true]);
    }

    public function test_the_push_services_are_configurable(): void {
        config()->set('matrix.webpush-hosts', ['push.example.test']);

        $this->withToken($this->token())
            ->postJson('api/member/push/subscribe', $this->payload(['endpoint' => 'https://push.example.test/device-1']))
            ->assertJson(['success' => true]);
    }

    public function test_the_same_endpoint_moves_to_the_member_who_subscribed_last(): void {
        $this->withToken($this->token(5))->postJson('api/member/push/subscribe', $this->payload());
        $this->withToken($this->token(6))->postJson('api/member/push/subscribe', $this->payload());

        $this->assertSame(6, PushSubscription::query()->sole()->member_id);
    }

    public function test_unsubscribing_removes_the_members_subscription(): void {
        $token = $this->token(5);

        $this->withToken($token)->postJson('api/member/push/subscribe', $this->payload());
        $this->withToken($token)
            ->postJson('api/member/push/unsubscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1'])
            ->assertJson(['success' => true]);

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_unsubscribing_does_not_remove_another_members_subscription(): void {
        $this->withToken($this->token(5))->postJson('api/member/push/subscribe', $this->payload());

        $this->withToken($this->token(6))->postJson('api/member/push/unsubscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1']);

        $this->assertSame(1, PushSubscription::query()->count());
    }

}
