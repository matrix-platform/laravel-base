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
            'endpoint' => 'https://push.example.test/device-1',
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

        $subscription = PushSubscription::query()->where('endpoint', 'https://push.example.test/device-1')->firstOrFail();

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

    public function test_unsubscribing_removes_the_members_subscription(): void {
        $token = $this->token(5);

        $this->withToken($token)->postJson('api/member/push/subscribe', $this->payload());
        $this->withToken($token)->postJson('api/member/push/unsubscribe', ['endpoint' => 'https://push.example.test/device-1'])->assertJson(['success' => true]);

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_unsubscribing_does_not_remove_another_members_subscription(): void {
        $this->withToken($this->token(5))->postJson('api/member/push/subscribe', $this->payload());

        $this->withToken($this->token(6))->postJson('api/member/push/unsubscribe', ['endpoint' => 'https://push.example.test/device-1']);

        $this->assertSame(1, PushSubscription::query()->count());
    }

}
