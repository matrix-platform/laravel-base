<?php //>

namespace Tests\Feature\Controllers;

use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Http\Controllers\Admin\UserController;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class UserControllerTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);

        Group::forceCreate(['title' => '管理員']);
    }

    protected function defineRoutes($router) {
        $router->middleware(UserMiddleware::class . ':admin')->prefix('admin')->group(function () use ($router) {
            $router->prefix('user')->controller(UserController::class)->scan();
        });
    }

    // --- list ---

    public function test_list_returns_users() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user');

        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('rows', $response->json('data'));
        $this->assertGreaterThan(0, count($response->json('data.rows')));
    }

    public function test_list_hides_root_for_non_root_user() {
        $response = $this->loginAs(User::ADMIN)->postJson('/admin/user');

        $response->assertJson(['success' => true]);

        $ids = collect($response->json('data.rows'))->pluck('id')->all();
        $this->assertNotContains(User::ROOT, $ids);
    }

    public function test_list_shows_root_for_root_user() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user');

        $response->assertJson(['success' => true]);

        $ids = collect($response->json('data.rows'))->pluck('id')->all();
        $this->assertContains(User::ROOT, $ids);
    }

    // --- new ---

    public function test_new_returns_form_columns() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user/new');

        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('columns', $response->json('data'));
        $this->assertArrayHasKey('actions', $response->json('data'));
    }

    // --- get ---

    public function test_get_returns_user_data() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user/2');

        $response->assertJson(['success' => true]);
        $this->assertEquals(User::ADMIN, $response->json('data.data.id'));
    }

    public function test_get_hides_root_for_non_root_user() {
        $response = $this->loginAs(User::ADMIN)->postJson('/admin/user/1');

        $this->assertFalse($response->json('success'));
    }

    // --- insert ---

    public function test_insert_creates_user() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user/insert', [
            'username' => 'newuser',
            'password' => null,
            'group_id' => null,
            'disabled' => false,
            'enable_time' => null,
            'disable_time' => null,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('id', $response->json('data'));
        $this->assertDatabaseHas('base_user', ['username' => 'newuser']);
    }

    public function test_insert_validation_requires_all_fields() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/user/insert', []);

        $this->assertFalse($response->json('success'));
        $this->assertEquals(422, $response->json('code'));
    }

    // --- update ---

    public function test_update_modifies_user() {
        $user = User::forceCreate(['username' => 'editme', 'disabled' => false]);

        $response = $this->loginAs(User::ROOT)->postJson("/admin/user/{$user->id}/update", [
            'disable_time' => null,
            'disabled' => true,
            'enable_time' => null,
            'group_id' => null,
            'password' => null,
            'username' => 'editme',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertTrue(User::find($user->id)->disabled);
    }

    public function test_update_username_is_writable() {
        $user = User::forceCreate(['username' => 'keepme', 'disabled' => false]);

        $response = $this->loginAs(User::ROOT)->postJson("/admin/user/{$user->id}/update", [
            'disable_time' => null,
            'disabled' => false,
            'enable_time' => null,
            'group_id' => null,
            'password' => null,
            'username' => 'changed',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals('changed', User::find($user->id)->username);
    }

    public function test_update_hides_root_for_non_root_user() {
        $response = $this->loginAs(User::ADMIN)->postJson('/admin/user/1/update', [
            'disable_time' => null,
            'disabled' => false,
            'enable_time' => null,
            'group_id' => null,
            'password' => null,
            'username' => 'root',
        ]);

        $this->assertFalse($response->json('success'));
    }

    // --- delete ---

    public function test_delete_removes_user() {
        $user = User::forceCreate(['username' => 'deleteme', 'disabled' => false]);

        $response = $this->loginAs(User::ROOT)->postJson('/admin/user/delete', [
            'id' => $user->id,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('base_user', ['id' => $user->id]);
    }

    public function test_delete_prevents_self_deletion() {
        $response = $this->loginAs(User::ADMIN)->postJson('/admin/user/delete', [
            'id' => User::ADMIN,
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertDatabaseHas('base_user', ['id' => User::ADMIN]);
    }

    public function test_delete_hides_root_for_non_root_user() {
        $response = $this->loginAs(User::ADMIN)->postJson('/admin/user/delete', [
            'id' => User::ROOT,
        ]);

        $this->assertFalse($response->json('success'));
        $this->assertDatabaseHas('base_user', ['id' => User::ROOT]);
    }

}
