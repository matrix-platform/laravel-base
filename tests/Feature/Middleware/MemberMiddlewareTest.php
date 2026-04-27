<?php //>

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MatrixPlatform\Http\Middleware\MemberMiddleware;
use MatrixPlatform\Models\Member;
use Tests\FeatureTestCase;

class MemberMiddlewareTest extends FeatureTestCase {

    protected function defineRoutes($router) {
        $router->middleware(MemberMiddleware::class)->get('/test-member', fn () => response()->json(['ok' => true]));
    }

    private function makeMember(array $attrs = []) {
        return Member::forceCreate(array_merge([
            'username' => 'member1',
            'status' => 1
        ], $attrs));
    }

    private function makeToken(int $memberId) {
        $token = Str::uuid()->toString();
        DB::table('base_auth_token')->insert([
            'token' => $token,
            'type' => 'Member',
            'target_id' => $memberId,
            'modify_time' => now()->startOfMinute(),
            'create_time' => now(),
            'expire_time' => null
        ]);
        return $token;
    }

    public function test_no_token_returns_401() {
        $response = $this->get('/test-member');
        $this->assertEquals(401, $response->json('code'));
    }

    public function test_member_with_status_not_1_returns_401() {
        $member = $this->makeMember(['status' => 0]);
        $token = $this->makeToken($member->id);

        $response = $this->withToken($token)->get('/test-member');
        $this->assertEquals(401, $response->json('code'));
    }

    public function test_valid_cookie_passes_and_sets_actor() {
        $member = $this->makeMember();
        $token = $this->makeToken($member->id);

        $response = $this->withUnencryptedCookie('matrix-member', $token)->get('/test-member');
        $this->assertTrue($response->json('ok'));
    }

    public function test_valid_bearer_token_passes_and_sets_actor() {
        $member = $this->makeMember();
        $token = $this->makeToken($member->id);

        $response = $this->withToken($token)->get('/test-member');
        $this->assertTrue($response->json('ok'));
    }

}
