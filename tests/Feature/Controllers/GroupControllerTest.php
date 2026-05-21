<?php //>

namespace Tests\Feature\Controllers;

use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Http\Middleware\UserMiddleware;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\User;
use Tests\FeatureTestCase;

class GroupControllerTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    protected function defineRoutes($router) {
        $router->middleware(UserMiddleware::class . ':admin')->prefix('admin')->group(function () use ($router) {
            $router->prefix('group')->controller(GroupController::class)->scan();
        });
    }

    // --- list ---

    public function test_list_returns_groups() {
        Group::forceCreate(['title' => '管理員']);

        $response = $this->loginAs(User::ROOT)->postJson('/admin/group');

        $response->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data.rows'));
    }

    // --- new ---

    public function test_new_returns_form_columns() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/group/new');

        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('columns', $response->json('data'));
    }

    // --- get ---

    public function test_get_returns_group_data() {
        $group = Group::forceCreate(['title' => '管理員']);

        $response = $this->loginAs(User::ROOT)->postJson("/admin/group/{$group->id}");

        $response->assertJson(['success' => true]);
        $this->assertEquals($group->id, $response->json('data.data.id'));
    }

    // --- insert ---

    public function test_insert_creates_group() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/group/insert', [
            'title' => '新群組',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('base_group', ['title' => '新群組']);
    }

    public function test_insert_validation_requires_all_fields() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/group/insert', []);

        $this->assertFalse($response->json('success'));
        $this->assertEquals(422, $response->json('code'));
    }

    // --- update ---

    public function test_update_modifies_group() {
        $group = Group::forceCreate(['title' => '舊名稱']);

        $response = $this->loginAs(User::ROOT)->postJson("/admin/group/{$group->id}/update", [
            'title' => '新名稱',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals('新名稱', Group::find($group->id)->title);
    }

    // --- delete ---

    public function test_delete_removes_group() {
        $group = Group::forceCreate(['title' => '刪除我']);

        $response = $this->loginAs(User::ROOT)->postJson('/admin/group/delete', [
            'id' => $group->id,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('base_group', ['id' => $group->id]);
    }

    public function test_delete_returns_error_for_nonexistent_id() {
        $response = $this->loginAs(User::ROOT)->postJson('/admin/group/delete', [
            'id' => 999999,
        ]);

        $this->assertFalse($response->json('success'));
    }

}
