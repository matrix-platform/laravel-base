<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;
use Tests\TestCase;

class AdminPermissionLevelTest extends TestCase {

    protected function defineEnvironment($app) {
        $app['config']->set('matrix.admin-menus', 'base');
        $app['config']->set('matrix.exclude-admin-menu-nodes', '');
        $app['config']->set('matrix.packages', 'base');
    }

    private function permission($userId) {
        $user = (object) ['group_id' => null, 'id' => $userId];

        return new AdminPermission($user);
    }

    public function test_root_user_id_1_sees_group_menu_nodes() {
        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertArrayHasKey('group', $nodes);
        $this->assertArrayHasKey('user', $nodes);
    }

    public function test_level_2_user_id_le_1000_sees_group_menu_nodes() {
        $nodes = $this->permission(User::ADMIN)->getMenuNodes();

        $this->assertArrayHasKey('group', $nodes);
        $this->assertArrayHasKey('user', $nodes);
    }

    public function test_level_3_user_id_gt_1000_cannot_see_group_menu_nodes_without_permission() {
        $nodes = $this->permission(10000001)->getMenuNodes();

        $this->assertArrayNotHasKey('group', $nodes);
        $this->assertArrayNotHasKey('user', $nodes);
    }

    public function test_boundary_id_1000_is_level_2() {
        $nodes = $this->permission(1000)->getMenuNodes();

        $this->assertArrayHasKey('group', $nodes);
        $this->assertArrayHasKey('user', $nodes);
    }

    public function test_boundary_id_1001_is_level_3() {
        $nodes = $this->permission(1001)->getMenuNodes();

        $this->assertArrayNotHasKey('group', $nodes);
        $this->assertArrayNotHasKey('user', $nodes);
    }

}
