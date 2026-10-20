<?php //>

namespace Tests\Feature\Services\Admin\Shared;

use MatrixPlatform\Models\Menu;
use MatrixPlatform\Services\Admin\Shared\SharedModelResolver;
use MatrixPlatform\Support\MenuLocks;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class SharedModelResolverTest extends FeatureTestCase {

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function actAs(array $permissions): void {
        actor()->setUser(UserFactory::new()->createOne(['id' => 1001, 'permissions' => $permissions]));
    }

    private function menu(?int $parentId): Menu {
        return MenuLocks::release(fn (): Menu => Menu::forceCreate(['parent_id' => $parentId, 'title__tw' => 'x', 'title__en' => 'x', 'ranking' => 1]));
    }

    private function resolver(): SharedModelResolver {
        return app(SharedModelResolver::class);
    }

    public function test_a_prefix_with_no_mounted_controller_is_refused_as_an_unsupported_model(): void {
        $this->actAsRoot();

        $this->refusesField('prefix', 'unsupported-model', fn () => $this->resolver()->resolve('nowhere', 1, 'query'));
    }

    public function test_a_missing_record_is_reported_as_not_found(): void {
        $this->actAsRoot();

        $this->refuses('data-not-found', fn () => $this->resolver()->resolve('group', 999999, 'query'));
    }

    public function test_a_regular_user_without_the_matching_permission_is_denied(): void {
        $group = GroupFactory::new()->createOne();

        actor()->setUser(UserFactory::new()->createOne(['id' => 1001, 'permissions' => []]));

        $this->refuses('permission-denied', fn () => $this->resolver()->resolve('group', $group->id, 'query'));
    }

    public function test_a_regular_user_with_the_matching_permission_is_allowed(): void {
        $group = GroupFactory::new()->createOne();

        actor()->setUser(UserFactory::new()->createOne(['id' => 1001, 'permissions' => ['group' => ['query' => true]]]));

        $this->assertSame($group->id, $this->resolver()->resolve('group', $group->id, 'query')->getKey());
    }

    public function test_root_bypasses_the_permission_check(): void {
        $group = GroupFactory::new()->createOne();

        $this->actAsRoot();

        $this->assertSame($group->id, $this->resolver()->resolve('group', $group->id, 'query')->getKey());
    }

    public function test_the_root_menu_permission_does_not_reach_a_child_menu(): void {
        $child = $this->menu($this->menu(null)->id);

        $this->actAs(['menu' => ['query' => true, 'update' => true]]);

        $this->refuses('permission-denied', fn () => $this->resolver()->resolve('menu/{parent_id}/children', $child->id, 'update'));
    }

    public function test_the_child_menu_permission_reaches_a_child_menu(): void {
        $child = $this->menu($this->menu(null)->id);

        $this->actAs(['menu/{parent_id}/children' => ['query' => true, 'update' => true]]);

        $this->assertSame($child->id, $this->resolver()->resolve('menu/{parent_id}/children', $child->id, 'update')->getKey());
    }

    public function test_a_root_menu_is_not_found_under_the_children_prefix(): void {
        $root = $this->menu(null);

        $this->actAs(['menu/{parent_id}/children' => ['query' => true, 'update' => true]]);

        $this->refuses('data-not-found', fn () => $this->resolver()->resolve('menu/{parent_id}/children', $root->id, 'update'));
    }

    public function test_a_child_menu_is_not_found_under_the_root_prefix(): void {
        $child = $this->menu($this->menu(null)->id);

        $this->actAs(['menu' => ['query' => true, 'update' => true]]);

        $this->refuses('data-not-found', fn () => $this->resolver()->resolve('menu', $child->id, 'update'));
    }

}
