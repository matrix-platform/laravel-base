<?php //>

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class UserMiddlewareTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    protected function defineRoutes($router) {
        $router->middleware(UserMiddleware::class)->get('/test-user', fn () => response()->json(['ok' => true]));
    }

    private function makeToken(User $user, array $tokenOverrides = []) {
        $token = \Illuminate\Support\Str::uuid()->toString();
        DB::table('base_auth_token')->insert(array_merge([
            'token' => $token,
            'type' => 'User',
            'target_id' => $user->id,
            'modify_time' => now()->startOfMinute(),
            'create_time' => now(),
            'expire_time' => null
        ], $tokenOverrides));
        return $token;
    }

    public function test_no_token_returns_401() {
        $response = $this->get('/test-user');
        $data = $response->json();
        $this->assertEquals(false, $data['success']);
        $this->assertEquals(401, $data['code']);
    }

    public function test_invalid_token_returns_401() {
        $response = $this->withToken('invalid')->get('/test-user');
        $data = $response->json();
        $this->assertEquals(401, $data['code']);
    }

    public function test_expired_token_returns_401() {
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user, ['expire_time' => now()->subMinute()]);

        $response = $this->withToken($token)->get('/test-user');
        $this->assertEquals(401, $response->json('code'));
    }

    public function test_disabled_user_returns_401() {
        $user = User::find(User::ADMIN);
        $user->disabled = true;
        $user->save();
        $token = $this->makeToken($user);

        $response = $this->withToken($token)->get('/test-user');
        $this->assertEquals(401, $response->json('code'));
    }

    public function test_inactive_user_returns_401() {
        $user = User::find(User::ADMIN);
        $user->enable_time = now()->addHour();
        $user->save();
        $token = $this->makeToken($user);

        $response = $this->withToken($token)->get('/test-user');
        $this->assertEquals(401, $response->json('code'));
    }

    public function test_valid_bearer_token_passes_and_sets_actor() {
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user);

        $response = $this->withToken($token)->get('/test-user');
        $this->assertTrue($response->json('ok'));
    }

    public function test_valid_cookie_passes_and_sets_actor() {
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user);

        $response = $this->withUnencryptedCookie('matrix-user', $token)->get('/test-user');
        $this->assertTrue($response->json('ok'));
    }

    public function test_expire_time_is_renewed_when_token_has_no_expiry() {
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user, ['expire_time' => null]);

        $this->withToken($token)->get('/test-user');

        $record = DB::table('base_auth_token')->where('token', $token)->first();
        $this->assertNotNull($record->expire_time);
        $this->assertGreaterThan(now()->toDateTimeString(), $record->expire_time);
    }

    public function test_token_is_touched_when_not_current_minute() {
        $ttl = cfg('admin.token-ttl-days');
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user, [
            'expire_time' => now()->addDays($ttl + 1),
            'modify_time' => now()->subMinutes(5),
        ]);

        $this->withToken($token)->get('/test-user');

        $record = DB::table('base_auth_token')->where('token', $token)->first();
        $this->assertEquals(now()->startOfMinute()->toDateTimeString(), $record->modify_time);
    }

    public function test_token_is_not_touched_when_already_current_minute() {
        $ttl = cfg('admin.token-ttl-days');
        $user = User::find(User::ROOT);
        $token = $this->makeToken($user, [
            'expire_time' => now()->addDays($ttl + 1),
            'modify_time' => now()->startOfMinute(),
        ]);

        $before = DB::table('base_auth_token')->where('token', $token)->first()->modify_time;
        $this->withToken($token)->get('/test-user');
        $after = DB::table('base_auth_token')->where('token', $token)->first()->modify_time;

        $this->assertEquals($before, $after);
    }

}
