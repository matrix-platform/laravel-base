<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\Vendor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\PremiumMember;
use Tests\Stubs\StubDeclaration;

class IdentityMiddlewareTest extends FeatureTestCase {

    private const SHARED = 90001;

    /**
     * @return array<string, array{IdentityType}>
     */
    public static function identities(): array {
        return [
            'member' => [IdentityType::Member],
            'user' => [IdentityType::User],
            'vendor' => [IdentityType::Vendor]
        ];
    }

    protected function defineRoutes($router): void {
        $slots = function (): array {
            $current = actor()->current();

            return [
                'reached' => true,
                'member' => actor()->member()?->getKey(),
                'user' => actor()->user()?->getKey(),
                'vendor' => actor()->vendor()?->getKey(),
                'request' => request()->user()?->getKey(),
                'class' => $current === null ? null : $current::class
            ];
        };

        $router->middleware(['envelope-api'])
            ->prefix('identity')
            ->group(function () use ($router, $slots): void {
                $router->post('member', $slots)->middleware('member-api');
                $router->post('user', $slots)->middleware('user-api');
                $router->post('vendor', $slots)->middleware('vendor-api');
                $router->post('aware', $slots)->middleware('member-aware-api');
                $router->post('stacked', $slots)->middleware(['member-aware-api', 'member-api']);
            });
    }

    private function disable(IdentityType $type, int $id): void {
        match ($type) {
            IdentityType::Member => Member::query()->whereKey($id)->update(['status' => 2]),
            IdentityType::User => User::query()->whereKey($id)->update(['disabled' => true]),
            IdentityType::Vendor => Vendor::query()->whereKey($id)->update(['status' => 2])
        };
    }

    private function idle(IdentityType $type): int {
        return (int) cfg("{$type->bundle()}.token-idle-minutes");
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function reach(IdentityType $type, string $token): TestResponse {
        return $this->withToken($token)->postJson('identity/' . strtolower($type->value));
    }

    private function slot(IdentityType $type): string {
        return strtolower($type->value);
    }

    private function token(IdentityType $type, int $id = self::SHARED): string {
        return match ($type) {
            IdentityType::Member => MemberFactory::new()->createOne(['id' => $id])->createToken(),
            IdentityType::User => UserFactory::new()->createOne(['id' => $id])->createToken(),
            IdentityType::Vendor => VendorFactory::new()->createOne(['id' => $id])->createToken()
        };
    }

    private function updateCount(callable $callback): int {
        return $this->queryCount('update "base_auth_token"', $callback);
    }

    #[DataProvider('identities')]
    public function test_a_bearer_token_reaches_the_endpoint(IdentityType $type): void {
        $response = $this->reach($type, $this->token($type));

        $response->assertJsonPath($this->slot($type), self::SHARED);
        $response->assertJsonPath('request', self::SHARED);
    }

    #[DataProvider('identities')]
    public function test_a_cookie_token_reaches_the_endpoint(IdentityType $type): void {
        $token = $this->token($type);

        $this->withCredentials()
            ->withUnencryptedCookie($type->cookie(), $token)
            ->postJson('identity/' . strtolower($type->value))
            ->assertJsonPath($this->slot($type), self::SHARED);
    }

    #[DataProvider('identities')]
    public function test_only_the_matching_actor_slot_is_filled(IdentityType $type): void {
        $response = $this->reach($type, $this->token($type));

        foreach (IdentityType::cases() as $other) {
            $response->assertJsonPath($this->slot($other), $other === $type ? self::SHARED : null);
        }
    }

    #[DataProvider('identities')]
    public function test_a_request_without_a_token_is_refused(IdentityType $type): void {
        $this->postJson('identity/' . strtolower($type->value))
            ->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    #[DataProvider('identities')]
    public function test_a_disabled_subject_is_refused(IdentityType $type): void {
        $token = $this->token($type);

        $this->disable($type, self::SHARED);

        $this->reach($type, $token)->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    #[DataProvider('identities')]
    public function test_a_session_survives_inside_the_idle_window(IdentityType $type): void {
        $token = $this->token($type);

        $this->travel($this->idle($type) - 1)->minutes();

        $this->reach($type, $token)->assertJsonPath($this->slot($type), self::SHARED);
    }

    #[DataProvider('identities')]
    public function test_a_session_left_idle_past_the_window_expires(IdentityType $type): void {
        $token = $this->token($type);

        $this->travel($this->idle($type) + 1)->minutes();

        $this->reach($type, $token)->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    #[DataProvider('identities')]
    public function test_the_token_is_touched_at_most_once_per_minute(IdentityType $type): void {
        $token = $this->token($type);

        $this->travel(1)->minutes();

        $updates = $this->updateCount(function () use ($type, $token): void {
            for ($hop = 0; $hop < 3; $hop++) {
                $this->reach($type, $token)->assertJsonPath($this->slot($type), self::SHARED);
            }
        });

        $this->assertSame(1, $updates);
    }

    public function test_a_token_issued_for_one_identity_is_refused_by_the_others(): void {
        $tokens = [];

        foreach (IdentityType::cases() as $type) {
            $tokens[$type->value] = $this->token($type);
        }

        foreach (IdentityType::cases() as $type) {
            foreach ($tokens as $issued => $token) {
                $response = $this->reach($type, $token);

                if ($issued === $type->value) {
                    $response->assertJsonPath($this->slot($type), self::SHARED);
                } else {
                    $response->assertJson(['code' => 401, 'error' => 'invalid-token']);
                }
            }
        }
    }

    public function test_the_aware_variant_lets_an_anonymous_request_through(): void {
        $response = $this->postJson('identity/aware');

        $response->assertJsonPath('reached', true);
        $response->assertJsonPath('member', null);
        $response->assertJsonPath('request', null);
    }

    public function test_the_aware_variant_carries_a_valid_member(): void {
        $response = $this->withToken($this->token(IdentityType::Member))->postJson('identity/aware');

        $response->assertJsonPath('reached', true);
        $response->assertJsonPath('member', self::SHARED);
    }

    public function test_the_aware_variant_lets_an_expired_token_through(): void {
        $token = $this->token(IdentityType::Member);

        $this->travel($this->idle(IdentityType::Member) + 1)->minutes();

        $response = $this->withToken($token)->postJson('identity/aware');

        $response->assertJsonPath('reached', true);
        $response->assertJsonPath('member', null);
    }

    public function test_the_aware_variant_lets_a_disabled_member_through(): void {
        $token = $this->token(IdentityType::Member);

        $this->disable(IdentityType::Member, self::SHARED);

        $response = $this->withToken($token)->postJson('identity/aware');

        $response->assertJsonPath('reached', true);
        $response->assertJsonPath('member', null);
    }

    public function test_stacking_the_aware_variant_before_the_required_one_is_refused(): void {
        $token = $this->token(IdentityType::Member);

        $this->withToken($token)
            ->postJson('identity/stacked')
            ->assertJson(['success' => false, 'code' => 500, 'error' => 'actor-already-assigned']);
    }

    public function test_stacked_middleware_without_a_token_never_reaches_the_conflict(): void {
        $this->postJson('identity/stacked')
            ->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_a_member_model_pointing_at_a_missing_class_is_reported(): void {
        $token = $this->token(IdentityType::Member);

        config()->set('matrix.member-model', 'MatrixPlatform\Models\Nonexistent');

        $this->reach(IdentityType::Member, $token)
            ->assertJson(['success' => false, 'code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_member_model_pointing_at_a_non_model_class_is_reported(): void {
        $token = $this->token(IdentityType::Member);

        config()->set('matrix.member-model', StubDeclaration::class);

        $this->reach(IdentityType::Member, $token)
            ->assertJson(['code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_member_model_unrelated_to_member_is_reported(): void {
        $token = $this->token(IdentityType::Member);

        config()->set('matrix.member-model', User::class);

        $this->reach(IdentityType::Member, $token)
            ->assertJson(['code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_member_model_set_to_null_is_reported(): void {
        $token = $this->token(IdentityType::Member);

        config()->set('matrix.member-model', null);

        $this->reach(IdentityType::Member, $token)
            ->assertJson(['code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_member_subclass_is_resolved_as_the_subclass(): void {
        $token = $this->token(IdentityType::Member);

        config()->set('matrix.member-model', PremiumMember::class);

        $response = $this->reach(IdentityType::Member, $token);

        $response->assertJsonPath('member', self::SHARED);
        $response->assertJsonPath('class', PremiumMember::class);
    }

    public function test_the_default_member_model_resolves_to_the_shipped_class(): void {
        $this->reach(IdentityType::Member, $this->token(IdentityType::Member))
            ->assertJsonPath('class', Member::class);
    }

    public function test_a_vendor_model_unrelated_to_vendor_is_reported(): void {
        $token = $this->token(IdentityType::Vendor);

        config()->set('matrix.vendor-model', Member::class);

        $this->reach(IdentityType::Vendor, $token)
            ->assertJson(['code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_vendor_model_pointing_at_a_missing_class_is_reported(): void {
        $token = $this->token(IdentityType::Vendor);

        config()->set('matrix.vendor-model', 'MatrixPlatform\Models\Nonexistent');

        $this->reach(IdentityType::Vendor, $token)
            ->assertJson(['code' => 500, 'error' => 'invalid-identity-model']);
    }

    public function test_a_configuration_error_stays_hidden_until_a_valid_token_arrives(): void {
        config()->set('matrix.member-model', 'MatrixPlatform\Models\Nonexistent');

        $this->postJson('identity/member')->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_every_access_pushes_the_idle_window_forward(): void {
        $token = $this->token(IdentityType::Member);
        $before = AuthToken::query()->where('token', $token)->firstOrFail()->update_time;

        $this->travel(2)->minutes();

        $this->reach(IdentityType::Member, $token)->assertJsonPath('member', self::SHARED);

        $after = AuthToken::query()->where('token', $token)->firstOrFail()->update_time;

        $this->assertNotNull($before);
        $this->assertNotNull($after);
        $this->assertTrue($after->gt($before));
    }

}
