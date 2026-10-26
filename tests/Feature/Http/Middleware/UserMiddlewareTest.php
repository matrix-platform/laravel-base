<?php //>

namespace Tests\Feature\Http\Middleware;

use MatrixPlatform\Models\AuthToken;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class UserMiddlewareTest extends FeatureTestCase {

    private function token(): string {
        return UserFactory::new()->createOne()->createToken();
    }

    private function idle(): int {
        return (int) cfg('admin.token-idle-minutes');
    }

    public function test_an_unknown_token_is_refused_with_a_200_envelope(): void {
        $response = $this->withToken('nonsense')->postJson('admin/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_an_expired_token_is_refused(): void {
        $token = $this->token();

        AuthToken::query()->where('token', AuthToken::digest($token))->update(['expire_time' => now()->subMinute()]);

        $this->withToken($token)
            ->postJson('admin/auth/logout')
            ->assertJson(['code' => 401]);
    }

    public function test_a_new_token_carries_no_absolute_expiry(): void {
        $auth = AuthToken::query()->where('token', AuthToken::digest($this->token()))->firstOrFail();

        $this->assertNull($auth->expire_time);
    }

    public function test_a_session_stays_alive_while_it_keeps_being_used(): void {
        $token = $this->token();

        for ($hop = 0; $hop < 3; $hop++) {
            $this->travel($this->idle() - 1)->minutes();

            $this->withToken($token)
                ->postJson('admin/auth/profile')
                ->assertJsonPath('success', true);
        }
    }

}
