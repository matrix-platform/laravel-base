<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\MenuLocks;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\TestLeafMenuFields;
use Tests\Stubs\TestLockedMenuFields;
use Tests\Stubs\TestLockingMenuTypeResolver;
use Tests\Stubs\TestSocialFields;
use Tests\Stubs\TestSyncedMenuFields;

class MenuLockTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->useVariants([
            'menu-data' => [
                'driver' => TestLockingMenuTypeResolver::class,
                'leaf' => TestLeafMenuFields::class,
                'locked' => TestLockedMenuFields::class,
                'social' => TestSocialFields::class,
                'synced' => TestSyncedMenuFields::class
            ]
        ]);

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function node(?int $parentId, string $title, int $ranking, ?array $data = null): Menu {
        return MenuLocks::release(function () use ($parentId, $title, $ranking, $data): Menu {
            $menu = new Menu();

            $menu->parent_id = $parentId;
            $menu->title__tw = $title;
            $menu->title__en = $title;
            $menu->data = $data;
            $menu->ranking = $ranking;
            $menu->enable_time = now()->subDay();

            $menu->save();

            return $menu;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    private function synced(Menu $parent, string $title = 'synced', int $ranking = 100): Menu {
        return $this->node($parent->id, $title, $ranking, ['kind' => 'synced', 'source' => 7]);
    }

    public function test_deleting_a_locked_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $locked = $this->node($root->id, 'locked', 100, ['kind' => 'locked']);

        $this->send("admin/menu/{$root->id}/children/delete", ['id' => [$locked->id]])
            ->assertJson(['success' => false, 'error' => 'menu-locked']);

        $this->assertNotNull(Menu::query()->find($locked->id));
    }

    public function test_a_locked_item_can_still_be_edited(): void {
        $root = $this->node(null, 'root', 100);
        $locked = $this->node($root->id, 'locked', 100, ['kind' => 'locked']);

        $this->send("admin/menu/{$root->id}/children/{$locked->id}/update", [
            'title__tw' => 'renamed',
            'title__en' => 'renamed',
            'data__kind' => 'locked',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJsonPath('success', true);

        $this->assertSame('renamed', $locked->refresh()->title__en);
    }

    public function test_deleting_a_synced_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $this->send("admin/menu/{$root->id}/children/delete", ['id' => [$synced->id]])
            ->assertJson(['success' => false, 'error' => 'menu-locked']);

        $this->assertNotNull(Menu::query()->find($synced->id));
    }

    public function test_updating_a_synced_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $this->send("admin/menu/{$root->id}/children/{$synced->id}/update", [
            'title__tw' => 'renamed',
            'title__en' => 'renamed',
            'data__kind' => 'synced',
            'data__source' => 7,
            'enable_time' => null,
            'disable_time' => null
        ])->assertJson(['success' => false, 'error' => 'menu-locked']);

        $this->assertSame('synced', $synced->refresh()->title__en);
    }

    public function test_dropping_the_synced_marker_through_an_update_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $synced->data = null;

        $this->expectException(ServiceException::class);

        $synced->save();
    }

    public function test_toggling_the_schedule_of_a_synced_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $this->send('admin/schedule/toggle', ['prefix' => 'menu', 'id' => $synced->id, 'enabled' => false])
            ->assertJson(['success' => false, 'error' => 'menu-locked']);

        $this->assertNull($synced->refresh()->disable_time);
    }

    public function test_inserting_under_a_synced_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $this->send("admin/menu/{$synced->id}/children/insert", [
            'title__tw' => 'child',
            'title__en' => 'child',
            'data__platform' => 'facebook',
            'data__url' => 'https://facebook.com/example',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJson(['success' => false, 'error' => 'menu-depth-exceeded']);

        $this->assertSame(0, Menu::query()->where('parent_id', $synced->id)->count());
    }

    public function test_inserting_under_a_leaf_item_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $leaf = $this->node($root->id, 'leaf', 100, ['kind' => 'leaf']);

        $this->send("admin/menu/{$leaf->id}/children/insert", [
            'title__tw' => 'child',
            'title__en' => 'child',
            'data__platform' => 'facebook',
            'data__url' => 'https://facebook.com/example',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJson(['success' => false, 'error' => 'menu-depth-exceeded']);

        $this->assertSame(0, Menu::query()->where('parent_id', $leaf->id)->count());
    }

    public function test_a_leaf_item_can_still_be_edited_and_deleted(): void {
        $root = $this->node(null, 'root', 100);
        $leaf = $this->node($root->id, 'leaf', 100, ['kind' => 'leaf']);

        $this->send("admin/menu/{$root->id}/children/delete", ['id' => [$leaf->id]])->assertJsonPath('success', true);

        $this->assertNull(Menu::query()->find($leaf->id));
    }

    public function test_creating_a_synced_item_outside_a_release_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $menu = new Menu();

        $menu->parent_id = $root->id;
        $menu->title__tw = 'copy';
        $menu->data = ['kind' => 'synced', 'source' => 7];

        $this->expectException(ServiceException::class);

        $menu->save();
    }

    public function test_writes_inside_a_release_bypass_the_locks(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        MenuLocks::release(function () use ($synced): void {
            $synced->title__en = 'synced again';
            $synced->disable_time = now();
            $synced->save();
        });

        $this->assertSame('synced again', $synced->refresh()->title__en);

        MenuLocks::release(fn () => $synced->delete());

        $this->assertNull(Menu::query()->find($synced->id));
    }

    public function test_arranging_reorders_a_synced_item_and_reports_it_as_fixed(): void {
        $root = $this->node(null, 'root', 100);
        $plain = $this->node($root->id, 'plain', 100, ['platform' => 'line', 'url' => 'https://line.me']);
        $synced = $this->synced($root, 'synced', 200);

        $rows = $this->send("admin/menu/{$root->id}/children/arrange")->json('data.rows');

        $this->assertSame([false, true], array_column($rows, 'fixed'));

        $this->send("admin/menu/{$root->id}/children/arrange/save", ['enabled' => [$synced->id, $plain->id]])
            ->assertJsonPath('success', true);

        $this->assertTrue($synced->refresh()->ranking < $plain->refresh()->ranking);
    }

    public function test_the_listing_offers_no_delete_button_on_locked_items(): void {
        $root = $this->node(null, 'root', 100);
        $plain = $this->node($root->id, 'plain', 100, ['platform' => 'line', 'url' => 'https://line.me']);
        $locked = $this->node($root->id, 'locked', 200, ['kind' => 'locked']);
        $synced = $this->synced($root, 'synced', 300);

        $actions = array_column($this->send("admin/menu/{$root->id}/children")->json('data.rows'), 'actions', 'id');

        $this->assertContains('delete', $actions[$plain->id]);
        $this->assertNotContains('delete', $actions[$locked->id]);
        $this->assertNotContains('delete', $actions[$synced->id]);
        $this->assertContains('edit', $actions[$synced->id]);
    }

    public function test_the_listing_reports_a_synced_item_as_fixed(): void {
        $root = $this->node(null, 'root', 100);
        $plain = $this->node($root->id, 'plain', 100, ['platform' => 'line', 'url' => 'https://line.me']);
        $synced = $this->synced($root, 'synced', 200);

        $rows = $this->send("admin/menu/{$root->id}/children")->json('data.rows');

        $this->assertSame([$plain->id => false, $synced->id => true], array_column($rows, 'fixed', 'id'));
    }

    public function test_arranging_a_synced_item_offline_is_refused(): void {
        $root = $this->node(null, 'root', 100);
        $plain = $this->node($root->id, 'plain', 100, ['platform' => 'line', 'url' => 'https://line.me']);
        $synced = $this->synced($root, 'synced', 200);

        $this->send("admin/menu/{$root->id}/children/arrange/save", ['enabled' => [$plain->id]])
            ->assertJson(['success' => false, 'error' => 'menu-locked']);

        $this->assertNull($synced->refresh()->disable_time);
    }

    public function test_the_edit_form_of_a_synced_item_is_read_only(): void {
        $root = $this->node(null, 'root', 100);
        $synced = $this->synced($root);

        $response = $this->send("admin/menu/{$root->id}/children/{$synced->id}")->assertJsonPath('success', true);
        $columns = array_column($response->json('data.columns'), 'readonly', 'name');

        $this->assertNotContains(false, $columns);
        $this->assertArrayHasKey('data__source', $columns);
        $this->assertNotContains('update', array_column($response->json('data.actions'), 'type'));
    }

    public function test_the_edit_form_of_a_plain_item_stays_writable(): void {
        $root = $this->node(null, 'root', 100);
        $plain = $this->node($root->id, 'plain', 100, ['platform' => 'line', 'url' => 'https://line.me']);

        $response = $this->send("admin/menu/{$root->id}/children/{$plain->id}")->assertJsonPath('success', true);
        $columns = array_column($response->json('data.columns'), 'readonly', 'name');

        $this->assertFalse($columns['title']);
        $this->assertFalse($columns['data__url']);
        $this->assertContains('update', array_column($response->json('data.actions'), 'type'));
    }

    public function test_the_listing_hides_the_children_count_of_leaf_and_synced_items(): void {
        $root = $this->node(null, 'root', 100);
        $locked = $this->node($root->id, 'locked', 100, ['kind' => 'locked']);
        $leaf = $this->node($root->id, 'leaf', 200, ['kind' => 'leaf']);
        $synced = $this->synced($root, 'synced', 300);

        $this->node($locked->id, 'grandchild', 100, ['platform' => 'line', 'url' => 'https://line.me']);

        $rows = $this->send("admin/menu/{$root->id}/children")->json('data.rows');
        $counts = array_column($rows, 'children_count', 'id');

        $this->assertSame(1, $counts[$locked->id]);
        $this->assertNull($counts[$leaf->id]);
        $this->assertNull($counts[$synced->id]);
    }

}
