<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class PermissionMiddlewareTest extends FeatureTestCase {

    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api', 'permission-api'])
            ->prefix('admin')
            ->group(function () use ($router): void {
                $router->post('user', fn () => ['reached' => true]);
                $router->post('user/insert', fn () => ['reached' => true]);
            });
    }

    protected function setUp(): void {
        parent::setUp();

        Storage::fake();

        $this->useMenuFixtures('authority resource');
    }

    private function token(int $id, ?int $groupId = null): string {
        return UserFactory::new()->createOne(['id' => $id, 'group_id' => $groupId])->createToken();
    }

    public function test_root_reaches_the_endpoint(): void {
        $this->withToken($this->token(User::ROOT))
            ->postJson('admin/user')
            ->assertJsonPath('reached', true);
    }

    public function test_a_regular_user_without_grants_is_refused_with_a_200_envelope(): void {
        $response = $this->withToken($this->token(2000))->postJson('admin/user');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_a_granted_regular_user_reaches_the_endpoint(): void {
        Storage::put('permission/User/2000', (string) json_encode(['user' => ['query' => true]]));

        $this->withToken($this->token(2000))
            ->postJson('admin/user')
            ->assertJsonPath('reached', true);
    }

    public function test_the_grant_is_scoped_to_the_action_that_was_granted(): void {
        Storage::put('permission/User/2000', (string) json_encode(['user' => ['query' => true]]));

        $this->withToken($this->token(2000))
            ->postJson('admin/user/insert')
            ->assertJson(['code' => 403]);
    }

    public function test_an_anonymous_request_is_refused_before_the_permission_check(): void {
        $this->postJson('admin/user')->assertJson(['code' => 401, 'error' => 'invalid-token']);
    }

}
