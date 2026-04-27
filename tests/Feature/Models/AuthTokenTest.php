<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\AuthToken;
use Tests\FeatureTestCase;

class AuthTokenTest extends FeatureTestCase {

    private function makeToken(array $overrides = []) {
        DB::table('base_auth_token')->insert(array_merge([
            'token' => 'test-token',
            'type' => 'User',
            'target_id' => 1,
            'modify_time' => now(),
            'create_time' => now(),
            'expire_time' => null
        ], $overrides));

        return AuthToken::where('token', $overrides['token'] ?? 'test-token')->first();
    }

    public function test_find_by_token_returns_token_for_valid_type() {
        $this->makeToken(['token' => 'abc', 'type' => 'User']);

        $result = AuthToken::findByToken('abc', 'User');

        $this->assertNotNull($result);
        $this->assertEquals('abc', $result->token);
    }

    public function test_find_by_token_returns_null_for_wrong_type() {
        $this->makeToken(['token' => 'abc', 'type' => 'User']);

        $this->assertNull(AuthToken::findByToken('abc', 'Member'));
    }

    public function test_find_by_token_returns_null_for_expired_token() {
        $this->makeToken(['token' => 'abc', 'expire_time' => now()->subMinute()]);

        $this->assertNull(AuthToken::findByToken('abc', 'User'));
    }

    public function test_find_by_token_returns_null_for_null_token() {
        $this->assertNull(AuthToken::findByToken(null, 'User'));
    }

    public function test_fresh_timestamp_truncates_to_start_of_minute() {
        $token = new AuthToken;
        $ts = $token->freshTimestamp();

        $this->assertEquals(0, $ts->second);
        $this->assertEquals(now()->startOfMinute()->toDateTimeString(), $ts->toDateTimeString());
    }

}
