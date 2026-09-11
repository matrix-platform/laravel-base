<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class MenuControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function node(?int $parentId, string $title, int $ranking): Menu {
        $menu = new Menu();

        $menu->parent_id = $parentId;
        $menu->title__tw = $title;
        $menu->title__en = $title;
        $menu->ranking = $ranking;
        $menu->enable_time = now()->subDay();

        $menu->save();

        return $menu;
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    private function driveImage(): DriveNode {
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::File;
        $node->name = 'cover.jpg';
        $node->hash = 'hash-cover.jpg';
        $node->path = date('Ym') . '/' . str()->random(32);
        $node->size = 100;
        $node->mime_type = 'image/jpeg';
        $node->width = 400;
        $node->height = 300;

        $node->save();

        return $node;
    }

    public function test_the_listing_reports_each_rows_title(): void {
        $this->node(null, 'Header Menu', 100);

        $this->send('admin/menu')->assertJsonPath('data.rows.0.title', 'Header Menu');
    }

    public function test_the_listing_without_a_parent_id_reports_only_the_root_nodes(): void {
        $root = $this->node(null, 'root', 100);

        $this->node($root->id, 'child', 100);
        $this->node(null, 'other-root', 200);

        $titles = array_column($this->send('admin/menu')->json('data.rows'), 'title');

        $this->assertSame(['root', 'other-root'], $titles);
    }

    public function test_the_child_route_drills_into_that_nodes_children_at_any_depth(): void {
        $root = $this->node(null, 'root', 100);
        $child = $this->node($root->id, 'child', 100);

        $this->node($child->id, 'grandchild', 100);
        $this->node(null, 'other-root', 200);

        $titles = array_column($this->send("admin/menu/{$root->id}/children")->json('data.rows'), 'title');

        $this->assertSame(['child'], $titles);

        $deeper = array_column($this->send("admin/menu/{$child->id}/children")->json('data.rows'), 'title');

        $this->assertSame(['grandchild'], $deeper);
    }

    public function test_inserting_on_the_child_route_attaches_the_node_to_that_parent(): void {
        $root = $this->node(null, 'root', 100);

        $this->send("admin/menu/{$root->id}/children/insert", [
            'title__tw' => '子選單',
            'title__en' => 'Child Menu',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJsonPath('success', true);

        $created = Menu::query()->where('title__en', 'Child Menu')->sole();

        $this->assertSame($root->id, $created->parent_id);
        $this->assertSame(['root'], array_column($this->send('admin/menu')->json('data.rows'), 'title'));
        $this->assertSame(['Child Menu'], array_column($this->send("admin/menu/{$root->id}/children")->json('data.rows'), 'title'));
    }

    public function test_the_listing_excludes_the_raw_json_data_column_but_reports_the_children_count(): void {
        $root = $this->node(null, 'root', 100);

        $this->node($root->id, 'child', 100);

        $response = $this->send('admin/menu');

        $this->assertSame(['title', 'children_count'], array_column($response->json('data.columns'), 'name'));
        $this->assertSame(1, $response->json('data.rows.0.children_count'));
    }

    public function test_a_node_without_children_reports_a_zero_count_rather_than_null(): void {
        $this->node(null, 'lonely', 100);

        $this->send('admin/menu')->assertJsonPath('data.rows.0.children_count', 0);
    }

    public function test_the_actions_of_a_child_listing_stay_on_the_child_route(): void {
        $root = $this->node(null, 'root', 100);

        $actions = $this->send("admin/menu/{$root->id}/children")->json('data.actions.page');

        $this->assertSame(
            ['menu/{parent_id}/children/new', 'menu/{parent_id}/children/delete', 'menu/{parent_id}/children/arrange'],
            array_column($actions, 'url')
        );
    }

    public function test_the_actions_of_the_root_listing_stay_on_the_root_route(): void {
        $this->node(null, 'root', 100);

        $actions = $this->send('admin/menu')->json('data.actions.page');

        $this->assertSame(['menu/new', 'menu/delete', 'menu/arrange'], array_column($actions, 'url'));
    }

    public function test_the_children_count_derives_its_drill_down_path_from_the_relation_name(): void {
        $root = $this->node(null, 'root', 100);

        foreach (['admin/menu', "admin/menu/{$root->id}/children"] as $uri) {
            $columns = $this->send($uri)->json('data.columns');
            $index = array_search('children_count', array_column($columns, 'name'), true);

            $this->assertNotFalse($index, "children_count is missing from {$uri}");
            $this->assertSame('menu/{id}/children', $columns[$index]['path'], "wrong drill-down path on {$uri}");
        }
    }

    public function test_the_breadcrumbs_of_a_listing_name_every_ancestor_level(): void {
        $root = $this->node(null, 'root', 100);
        $child = $this->node($root->id, 'child', 100);

        $crumbs = $this->send("admin/menu/{$child->id}/children")->json('data.breadcrumbs');

        $this->assertSame([null, 'root', 'child'], array_column($crumbs, 'label'));
        $this->assertSame(['menu', "menu/{$root->id}/children", "menu/{$child->id}/children"], array_column($crumbs, 'path'));
    }

    public function test_the_first_child_level_names_its_parent_the_same_way_deeper_levels_do(): void {
        $root = $this->node(null, 'root', 100);

        $crumbs = $this->send("admin/menu/{$root->id}/children")->json('data.breadcrumbs');

        $this->assertSame([null, 'root'], array_column($crumbs, 'label'));
        $this->assertSame(['menu', "menu/{$root->id}/children"], array_column($crumbs, 'path'));
    }

    public function test_the_breadcrumbs_keep_naming_levels_however_deep_the_tree_goes(): void {
        $root = $this->node(null, 'root', 100);
        $child = $this->node($root->id, 'child', 100);
        $grandchild = $this->node($child->id, 'grandchild', 100);
        $great = $this->node($grandchild->id, 'great', 100);

        $crumbs = $this->send("admin/menu/{$grandchild->id}/children/{$great->id}")->json('data.breadcrumbs');

        $this->assertSame([null, 'root', 'child', 'grandchild', 'great'], array_column($crumbs, 'label'));
        $this->assertSame([
            'menu',
            "menu/{$root->id}/children",
            "menu/{$child->id}/children",
            "menu/{$grandchild->id}/children",
            "menu/{$grandchild->id}/children/{$great->id}"
        ], array_column($crumbs, 'path'));
    }

    public function test_the_new_form_includes_the_composite_data_field(): void {
        $names = array_column($this->send('admin/menu/new')->json('data.columns'), 'name');

        $this->assertSame(['title', 'data', 'enable_time', 'disable_time'], $names);
    }

    public function test_the_new_form_resolves_a_different_variant_depending_on_the_submitted_parent_id(): void {
        $this->useMenuDataFixtures();

        $withoutParent = $this->send('admin/menu/new')->json('data.columns');
        $header = $withoutParent[array_search('data', array_column($withoutParent, 'name'), true)];

        $this->assertSame(['caption', 'image'], array_column($header['variant'], 'name'));

        $withParent = $this->send('admin/menu/new', ['parent_id' => 999])->json('data.columns');
        $social = $withParent[array_search('data', array_column($withParent, 'name'), true)];

        $this->assertSame(['platform', 'url'], array_column($social['variant'], 'name'));
    }

    public function test_inserting_creates_the_row_with_the_resolved_variants_data(): void {
        $this->useMenuDataFixtures();

        $node = $this->driveImage();

        $this->send('admin/menu/insert', [
            'parent_id' => null,
            'title__tw' => '頁尾選單',
            'title__en' => 'Footer Menu',
            'data' => ['caption' => ['tw' => '頁尾說明', 'en' => 'Footer caption'], 'image' => [['id' => $node->id]]],
            'enable_time' => null,
            'disable_time' => null
        ])->assertJsonPath('success', true);

        $menu = Menu::query()->where('title__en', 'Footer Menu')->sole();
        $data = $menu->data;

        $this->assertIsArray($data);
        $this->assertSame('頁尾說明', $data['caption']['tw']);
        $this->assertSame('Footer caption', $data['caption']['en']);
        $this->assertSame(File::DRIVE_PREFIX . $node->path, $data['image'][0]['path']);
    }

    public function test_inserting_without_a_configured_driver_leaves_data_untouched(): void {
        $this->send('admin/menu/insert', [
            'parent_id' => null,
            'title__tw' => '無設定選單',
            'title__en' => 'Unconfigured Menu',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJsonPath('success', true);

        $menu = Menu::query()->where('title__en', 'Unconfigured Menu')->sole();

        $this->assertNull($menu->data);
    }

    public function test_an_invalid_type_resolver_driver_is_refused_cleanly(): void {
        $this->useMenuDataFixtures();
        $this->useCfg('menu-data', ['driver' => Menu::class]);

        $response = $this->send('admin/menu/new');

        $response->assertJsonPath('code', 500);
        $response->assertJsonPath('error', 'invalid-type-resolver');
    }

    public function test_deleting_a_node_with_children_is_refused(): void {
        $root = $this->node(null, 'root', 100);

        $this->node($root->id, 'child', 100);

        $response = $this->send('admin/menu/delete', ['id' => [$root->id]]);

        $response->assertJsonPath('error', 'data-in-use');
        $this->assertNotNull(Menu::query()->find($root->id));
    }

    public function test_arranging_only_reorders_siblings_under_the_same_parent(): void {
        $root = $this->node(null, 'root', 100);

        $firstChild = $this->node($root->id, 'first', 100);
        $secondChild = $this->node($root->id, 'second', 200);

        $other = $this->node(null, 'other-root', 200);

        $response = $this->send("admin/menu/{$root->id}/children/arrange");

        $this->assertSame([$firstChild->id, $secondChild->id], array_column($response->json('data.rows'), 'id'));

        $this->send("admin/menu/{$root->id}/children/arrange/save", ['enabled' => [$secondChild->id, $firstChild->id]])
            ->assertJsonPath('success', true);

        $this->assertTrue($secondChild->refresh()->ranking < $firstChild->refresh()->ranking);
        $this->assertSame(200, $other->refresh()->ranking);
    }

    public function test_the_independent_sort_action_is_not_exposed_in_favor_of_arrange(): void {
        $this->node(null, 'root', 100);

        $this->send('admin/menu/sort')->assertJsonPath('error', 'permission-denied');
    }

}
