<?php //>

namespace Tests\Feature\Http\Middleware;

use MatrixPlatform\Models\AuthToken;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class UserMiddlewareTest extends FeatureTestCase {

    private function token(): string {
        return UserFactory::new()->createOne()->createToken();
    }

    private function updateCount(callable $callback): int {
        return $this->queryCount('update "base_auth_token"', $callback);
    }

    public function test_a_valid_token_reaches_the_endpoint(): void {
        $this->withToken($this->token())
            ->postJson('admin/auth/profile')
            ->assertJsonPath('success', true);
    }

    public function test_an_unknown_token_is_refused_with_a_200_envelope(): void {
        $response = $this->withToken('nonsense')->postJson('admin/auth/profile');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_an_expired_token_is_refused(): void {
        $token = $this->token();

        AuthToken::query()->where('token', $token)->update(['expire_time' => now()->subMinute()]);

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJson(['code' => 401]);
    }

    public function test_a_token_whose_user_became_disabled_is_refused(): void {
        $user = UserFactory::new()->createOne();
        $token = $user->createToken();

        $user->disabled = true;
        $user->save();

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJson(['code' => 401]);
    }

    public function test_the_token_is_touched_at_most_once_per_minute(): void {
        $token = $this->token();

        $this->travel(1)->minutes();

        $updates = $this->updateCount(function () use ($token): void {
            for ($hop = 0; $hop < 3; $hop++) {
                $this->withToken($token)
                    ->postJson('admin/auth/profile')
                    ->assertJsonPath('success', true);
            }
        });

        $this->assertSame(1, $updates);
    }

    public function test_a_new_token_carries_no_absolute_expiry(): void {
        $auth = AuthToken::query()->where('token', $this->token())->firstOrFail();

        $this->assertNull($auth->expire_time);
        $this->assertNotNull($auth->update_time);
    }

    public function test_each_access_pushes_the_idle_window_forward(): void {
        $token = $this->token();
        $before = AuthToken::query()->where('token', $token)->firstOrFail()->update_time;

        $this->travel(2)->minutes();

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJsonPath('success', true);

        $after = AuthToken::query()->where('token', $token)->firstOrFail()->update_time;

        $this->assertNotNull($before);
        $this->assertNotNull($after);
        $this->assertTrue($after->gt($before));
    }

    public function test_a_session_stays_alive_while_it_keeps_being_used(): void {
        $token = $this->token();

        for ($hop = 0; $hop < 3; $hop++) {
            $this->travel(20)->minutes();

            $this->withToken($token)
                ->postJson('admin/auth/profile')
                ->assertJsonPath('success', true);
        }
    }

    public function test_a_session_left_idle_past_the_window_expires(): void {
        $token = $this->token();

        $this->travel(31)->minutes();

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

    public function test_the_actor_carries_the_authenticated_user(): void {
        $user = UserFactory::new()->createOne(['username' => 'carol']);

        $this->withToken($user->createToken())
            ->postJson('admin/auth/profile')
            ->assertJsonPath('data.profile.username', 'carol');
    }

}
