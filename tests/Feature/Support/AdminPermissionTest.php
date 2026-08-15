<?php //>

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Resources;
use Tests\FeatureTestCase;

class AdminPermissionTest extends FeatureTestCase {

    private const ADMIN = 500;
    private const REGULAR = 2000;

    /**
     * @var array<int, array<string, array<string, bool>>>
     */
    private array $granted = [];

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('authority resource');
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function grantGroup(int $id, array $permissions): void {
        Group::forceCreate(['id' => $id, 'title' => "group-{$id}", 'permissions' => $permissions]);
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function grantUser(int $id, array $permissions): void {
        $this->granted[$id] = $permissions;
    }

    private function listing(?string $menus): void {
        config()->set('matrix.admin-menus', $menus);

        app()->forgetInstance(Menus::class);
    }

    private function override(): void {
        app(PackageRegistry::class)->register('menu-override', __DIR__ . '/../../fixtures/package-menu-override');

        config()->set('matrix.packages', 'menu-override menu-fixture app base');

        app()->forgetInstance(Menus::class);
        app()->forgetInstance(Resources::class);
    }

    private function permission(int $id, ?int $groupId = null): AdminPermission {
        $user = new User();

        $user->id = $id;
        $user->group_id = $groupId;
        $user->permissions = array_key_exists($id, $this->granted) ? $this->granted[$id] : [];

        return new AdminPermission($user, app(Menus::class));
    }

    private function route(string $uri): void {
        $route = new Route(['POST'], $uri, fn () => null);

        $route->bind(Request::create("/{$uri}", 'POST'));

        request()->setRouteResolver(fn () => $route);
    }

    public function test_root_reaches_a_grouped_menu(): void {
        $this->route('admin/user');

        $this->assertSame('user', $this->permission(User::ROOT)->getCurrentMenu()?->path);
    }

    public function test_an_unknown_path_resolves_to_no_menu(): void {
        $this->route('admin/nope');

        $this->assertNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_an_admin_reaches_a_grouped_menu_without_any_grant(): void {
        $this->route('admin/user');

        $this->assertNotNull($this->permission(self::ADMIN)->getCurrentMenu());
    }

    public function test_a_regular_user_without_grants_is_denied(): void {
        $this->route('admin/user');

        $this->assertNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_a_regular_user_with_a_personal_grant_is_allowed(): void {
        $this->grantUser(2000, ['user' => ['query' => true]]);

        $this->route('admin/user');

        $this->assertNotNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_a_group_grant_reaches_its_members(): void {
        $this->grantGroup(77, ['user' => ['query' => true]]);

        $this->route('admin/user');

        $this->assertNotNull($this->permission(self::REGULAR, 77)->getCurrentMenu());
    }

    public function test_a_personal_grant_is_merged_over_the_group_grant(): void {
        $this->grantGroup(77, ['user' => ['query' => true]]);
        $this->grantUser(2000, ['user' => ['delete' => true]]);

        $this->route('admin/user/delete');

        $this->assertNotNull($this->permission(self::REGULAR, 77)->getCurrentMenu());

        $this->route('admin/user');

        $this->assertNotNull($this->permission(self::REGULAR, 77)->getCurrentMenu());
    }

    public function test_a_group_grant_is_ignored_when_the_user_has_no_group(): void {
        $this->grantGroup(77, ['user' => ['query' => true]]);

        $this->route('admin/user');

        $this->assertNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_an_action_node_is_authorised_through_its_parent(): void {
        $this->grantUser(2000, ['user' => ['insert' => true]]);

        $this->route('admin/user/insert');

        $this->assertNotNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_an_action_node_is_denied_when_only_a_sibling_action_is_granted(): void {
        $this->grantUser(2000, ['user' => ['query' => true]]);

        $this->route('admin/user/insert');

        $this->assertNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_every_action_tag_is_enforced_independently(): void {
        $this->grantUser(2000, ['user' => ['update' => true]]);

        foreach (['user/{id}/update' => true, 'user/delete' => false, 'user/insert' => false, 'user/{id}' => false] as $path => $allowed) {
            $this->route("admin/{$path}");

            $this->assertSame($allowed, $this->permission(self::REGULAR)->getCurrentMenu() !== null, $path);
        }
    }

    public function test_a_system_tagged_node_is_reserved_for_root(): void {
        $this->route('admin/console');

        $this->assertNotNull($this->permission(User::ROOT)->getCurrentMenu());
        $this->assertNull($this->permission(self::ADMIN)->getCurrentMenu());
    }

    public function test_a_system_tagged_node_can_still_be_granted_explicitly(): void {
        $this->grantUser(500, ['console' => ['system' => true]]);

        $this->route('admin/console');

        $this->assertNotNull($this->permission(self::ADMIN)->getCurrentMenu());
    }

    public function test_a_user_tagged_node_needs_no_grant_at_all(): void {
        $this->route('admin/preference');

        $this->assertNotNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_a_tag_defined_by_the_host_package_can_be_granted(): void {
        $this->grantUser(2000, ['report' => ['export' => true]]);

        $this->route('admin/report');

        $this->assertNotNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_a_tag_defined_by_the_host_package_is_still_denied_without_a_grant(): void {
        $this->route('admin/report');

        $this->assertNull($this->permission(self::REGULAR)->getCurrentMenu());
        $this->assertNotNull($this->permission(self::ADMIN)->getCurrentMenu());
    }

    public function test_a_host_tag_reaches_the_action_nodes_through_their_parent(): void {
        $this->grantUser(2000, ['report' => ['export' => true]]);

        $this->route('admin/report/run');

        $this->assertNotNull($this->permission(self::REGULAR)->getCurrentMenu());
    }

    public function test_a_menu_bundle_that_is_not_listed_never_enters_the_system(): void {
        $this->listing('authority');

        $this->route('admin/resource');

        $this->assertNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_a_menu_bundle_that_is_not_listed_is_absent_from_the_navigation(): void {
        $this->listing('authority');

        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertArrayHasKey('user', $nodes);
        $this->assertArrayNotHasKey('resource', $nodes);
        $this->assertArrayNotHasKey('setting', $nodes);
    }

    public function test_no_menu_is_loaded_when_nothing_is_listed(): void {
        $this->listing(null);

        $this->route('admin/user');

        $this->assertSame([], $this->permission(User::ROOT)->getMenuNodes());
        $this->assertNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_a_listed_menu_bundle_is_reachable(): void {
        $this->route('admin/resource');

        $this->assertNotNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_each_bundle_carries_its_own_translations(): void {
        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertSame('Accounts', $nodes['user']['title']);
        $this->assertSame('Resource Files', $nodes['resource']['title']);
    }

    public function test_the_prefix_comes_from_the_configuration(): void {
        config()->set('matrix.admin-api-prefix', 'backend');

        $this->route('backend/user');

        $this->assertNotNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_the_prefix_must_match_from_the_start_of_the_uri(): void {
        $this->route('xadmin/user');

        $this->assertNull($this->permission(User::ROOT)->getCurrentMenu());
    }

    public function test_menu_nodes_only_carry_the_ranked_entries(): void {
        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertArrayHasKey('user', $nodes);
        $this->assertArrayNotHasKey('user/insert', $nodes);
    }

    public function test_menu_nodes_hide_the_resources_the_user_cannot_reach(): void {
        $this->grantUser(2000, ['user' => ['query' => true]]);

        $nodes = $this->permission(self::REGULAR)->getMenuNodes();

        $this->assertArrayHasKey('user', $nodes);
        $this->assertArrayHasKey('preference', $nodes);
        $this->assertArrayHasKey('system', $nodes);
        $this->assertArrayNotHasKey('group', $nodes);
        $this->assertArrayNotHasKey('console', $nodes);
    }

    public function test_an_earlier_package_overrides_the_menu_node(): void {
        $this->override();

        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertSame('override-icon', $nodes['user']['icon']);
        $this->assertSame(100, $nodes['user']['ranking']);
        $this->assertSame('Accounts', $nodes['user']['title']);
    }

    public function test_an_earlier_package_overrides_the_menu_translation(): void {
        $this->override();

        $nodes = $this->permission(User::ROOT)->getMenuNodes();

        $this->assertSame('Teams', $nodes['group']['title']);
    }

}
