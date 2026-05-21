<?php //>

namespace Tests\Feature\Middleware;

use MatrixPlatform\Http\Middleware\MemberAwareMiddleware;
use MatrixPlatform\Models\Member;
use Tests\FeatureTestCase;

class MemberAwareMiddlewareTest extends FeatureTestCase {

    protected function defineRoutes($router) {
        $router->middleware(MemberAwareMiddleware::class)->get('/test-aware', fn () => response()->json([
            'ok' => true,
            'member_id' => member()->id
        ]));
    }

    private function makeMember() {
        return Member::forceCreate(['username' => 'member1', 'status' => 1]);
    }

    public function test_no_token_passes_without_setting_actor() {
        $response = $this->get('/test-aware');
        $this->assertTrue($response->json('ok'));
        $this->assertNull($response->json('member_id'));
    }

    public function test_valid_token_sets_actor_and_passes() {
        $member = $this->makeMember();

        $response = $this->loginAsMember($member->id)->get('/test-aware');
        $this->assertTrue($response->json('ok'));
        $this->assertEquals($member->id, $response->json('member_id'));
    }

    public function test_invalid_token_passes_without_setting_actor() {
        $response = $this->withToken('invalid-token')->get('/test-aware');
        $this->assertTrue($response->json('ok'));
        $this->assertNull($response->json('member_id'));
    }

}
