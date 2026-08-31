<?php //>

namespace Tests\Feature\Services;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Services\CommonService;
use Tests\FeatureTestCase;

class CommonServiceTest extends FeatureTestCase {

    private function area(City $city, string $title, string $postCode, int $ranking): CityArea {
        $area = new CityArea();

        $area->city_id = $city->id;
        $area->title__en = $title;
        $area->post_code = $postCode;
        $area->ranking = $ranking;

        $area->save();

        return $area;
    }

    private function child(Menu $parent, string $title, int $ranking): Menu {
        $menu = $this->node($title, $ranking);

        $menu->parent_id = $parent->id;

        $menu->save();

        return $menu;
    }

    private function city(string $title, int $ranking): City {
        $city = new City();

        $city->title__en = $title;
        $city->ranking = $ranking;

        $city->save();

        return $city;
    }

    private function node(string $title, int $ranking): Menu {
        $menu = new Menu();

        $menu->title__en = $title;
        $menu->ranking = $ranking;
        $menu->enable_time = now()->subDay();

        $menu->save();

        return $menu;
    }

    private function service(): CommonService {
        return app(CommonService::class);
    }

    public function test_the_cities_come_back_ordered_by_ranking(): void {
        $this->city('Kaohsiung', 300);
        $this->city('Taichung', 200);
        $this->city('Taipei', 100);

        $this->assertSame(['Taipei', 'Taichung', 'Kaohsiung'], data_get($this->service()->city(), '*.title'));
    }

    public function test_the_areas_of_each_city_come_back_ordered_by_ranking(): void {
        $taipei = $this->city('Taipei', 100);
        $kaohsiung = $this->city('Kaohsiung', 200);

        $this->area($taipei, 'Daan', '106', 300);
        $this->area($taipei, 'Zhongzheng', '100', 100);
        $this->area($kaohsiung, 'Cijin', '805', 100);

        $payload = $this->service()->city();

        $this->assertSame(['Zhongzheng', 'Daan'], array_column($payload[0]['areas'], 'title'));
        $this->assertSame(['Cijin'], array_column($payload[1]['areas'], 'title'));
    }

    public function test_each_area_carries_only_its_identifier_title_and_post_code(): void {
        $taipei = $this->city('Taipei', 100);
        $area = $this->area($taipei, 'Daan', '106', 100);

        $payload = $this->service()->city();

        $this->assertSame([['id' => $area->id, 'title' => 'Daan', 'post_code' => '106']], $payload[0]['areas']);
    }

    public function test_the_roots_come_back_ordered_by_ranking(): void {
        $this->node('C', 300);
        $this->node('B', 200);
        $this->node('A', 100);

        $this->assertSame(['A', 'B', 'C'], data_get($this->service()->menu(null), '*.title'));
    }

    public function test_a_node_without_an_enable_time_is_excluded(): void {
        $this->node('scheduled', 100);

        $draft = $this->node('draft', 200);

        $draft->enable_time = null;

        $draft->save();

        $this->assertSame(['scheduled'], data_get($this->service()->menu(null), '*.title'));
    }

    public function test_a_node_enabled_in_the_future_is_excluded(): void {
        $this->node('live', 100);

        $pending = $this->node('pending', 200);

        $pending->enable_time = now()->addDay();

        $pending->save();

        $this->assertSame(['live'], data_get($this->service()->menu(null), '*.title'));
    }

    public function test_a_node_already_disabled_is_excluded(): void {
        $this->node('live', 100);

        $retired = $this->node('retired', 200);

        $retired->disable_time = now()->subHour();

        $retired->save();

        $this->assertSame(['live'], data_get($this->service()->menu(null), '*.title'));
    }

    public function test_the_root_call_nests_children_and_grandchildren(): void {
        $root = $this->node('root', 100);
        $child = $this->child($root, 'child', 100);

        $this->child($child, 'grandchild', 100);

        $tree = $this->service()->menu(null);

        $this->assertSame(['root'], data_get($tree, '*.title'));
        $this->assertSame(['child'], data_get($tree, '0.children.*.title'));
        $this->assertSame(['grandchild'], data_get($tree, '0.children.0.children.*.title'));
    }

    public function test_a_parent_call_returns_that_subtree_without_its_siblings(): void {
        $first = $this->node('first', 100);

        $this->node('second', 200);
        $this->child($first, 'own', 100);

        $this->assertSame(['own'], data_get($this->service()->menu($first->id), '*.title'));
    }

    public function test_every_node_carries_exactly_the_contracted_keys(): void {
        $root = $this->node('root', 100);

        $root->data = ['icon' => 'star'];

        $root->save();

        $leaf = $this->child($root, 'leaf', 100);

        $tree = $this->service()->menu(null);

        $this->assertSame(['id', 'title', 'data', 'children'], array_keys($tree[0]));
        $this->assertSame(['icon' => 'star'], $tree[0]['data']);
        $this->assertSame([], data_get($tree, '0.children.0.children', 'absent'));
        $this->assertSame($leaf->id, data_get($tree, '0.children.0.id'));
    }

    public function test_an_orphan_node_never_appears(): void {
        $this->node('root', 100);

        $orphan = $this->node('orphan', 200);

        DB::statement('ALTER TABLE base_menu DISABLE TRIGGER ALL');

        $orphan->parent_id = 9999999;

        $orphan->save();

        DB::statement('ALTER TABLE base_menu ENABLE TRIGGER ALL');

        $this->assertSame(['root'], data_get($this->service()->menu(null), '*.title'));
    }

    public function test_a_disabled_parent_hides_its_enabled_children_everywhere(): void {
        $parent = $this->node('parent', 100);

        $parent->enable_time = null;

        $parent->save();

        $this->child($parent, 'child', 100);

        $this->assertSame([], $this->service()->menu(null));
        $this->assertSame([], $this->service()->menu($parent->id));
    }

    public function test_a_self_referencing_cycle_returns_nothing_instead_of_recursing_forever(): void {
        $first = $this->node('first', 100);
        $second = $this->child($first, 'second', 200);

        $first->parent_id = $second->id;
        $first->save();

        $this->assertSame([], $this->service()->menu(null));
        $this->assertSame([], $this->service()->menu($first->id));
    }

}
