<?php //>

namespace Tests\Feature\Services\Admin;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Services\Admin\Common\ListService;
use MatrixPlatform\Support\AdminPermission;
use ReflectionClass;
use Tests\Fixtures\Models\CountItem;
use Tests\Fixtures\Models\CountOwner;
use Tests\FeatureTestCase;

class CountDrilldownTest extends FeatureTestCase {

    public function test_count_attaches_path_when_menu_has_target() {
        $this->stubMenus(['city-area' => ['title' => 'Areas']]);

        $column = $this->parseColumn(City::class, 'count(areas)');

        $this->assertSame('count', $column['type']);
        $this->assertSame('city-area', $column['path']);
    }

    public function test_count_omits_path_when_menu_missing() {
        $this->stubMenus([]);

        $column = $this->parseColumn(City::class, 'count(areas)');

        $this->assertSame('count', $column['type']);
        $this->assertArrayNotHasKey('path', $column);
    }

    public function test_count_attaches_parented_path_with_placeholder() {
        $this->stubMenus(['count-owner/{city_id}/count-item' => ['title' => 'Items']]);

        $column = $this->parseColumn(CountOwner::class, 'count(items)');

        $this->assertSame('count', $column['type']);
        $this->assertSame('count-owner/{city_id}/count-item', $column['path']);
    }

    public function test_count_omits_path_when_parented_menu_missing() {
        $this->stubMenus([]);

        $column = $this->parseColumn(CountOwner::class, 'count(items)');

        $this->assertSame('count', $column['type']);
        $this->assertArrayNotHasKey('path', $column);
    }

    public function test_nested_count_still_uses_menu_gate() {
        $this->stubMenus(['city-area' => ['title' => 'Areas']]);

        $column = $this->parseColumn(CityArea::class, 'count(city.areas)');

        $this->assertSame('count', $column['type']);
        $this->assertSame('city-area', $column['path']);
    }

    public function test_nested_count_omits_path_when_menu_missing() {
        $this->stubMenus([]);

        $column = $this->parseColumn(CityArea::class, 'count(city.areas)');

        $this->assertSame('count', $column['type']);
        $this->assertArrayNotHasKey('path', $column);
    }

    public function test_count_explicit_type_override_is_ignored() {
        $this->stubMenus([]);

        $column = $this->parseColumn(City::class, 'count(areas):integer');

        $this->assertSame('count', $column['type']);
    }

    public function test_sum_agg_type_unaffected() {
        $this->stubMenus([]);

        $column = $this->parseColumn(City::class, 'sum(areas.id)');

        $this->assertSame('integer', $column['type']);
        $this->assertArrayNotHasKey('path', $column);
    }

    public function test_count_column_default_op_is_between_and_sortable() {
        $this->stubMenus([]);

        $permission = app(AdminPermission::class);
        $permission->method('getCurrentMenu')->willReturn(null);

        $result = (new ListService(City::class))
            ->columns(['count(areas)'])
            ->params([])
            ->output([]);

        $column = collect($result['columns'])->firstWhere('name', 'areas_count');

        $this->assertSame('count', $column['type']);
        $this->assertSame('between', $column['op']);
        $this->assertTrue($column['sortable']);
    }

    public function test_count_column_filter_between_applies_to_subquery() {
        $this->stubMenus([]);

        $permission = app(AdminPermission::class);
        $permission->method('getCurrentMenu')->willReturn(null);

        $low = City::forceCreate(['title' => 'low']);
        $mid = City::forceCreate(['title' => 'mid']);
        $high = City::forceCreate(['title' => 'high']);

        foreach ([[$low, 1], [$mid, 4], [$high, 9]] as [$city, $count]) {
            for ($i = 0; $i < $count; $i++) {
                CityArea::forceCreate(['city_id' => $city->id, 'post_code' => 'x', 'title' => "{$city->title}-{$i}"]);
            }
        }

        $result = (new ListService(City::class))
            ->columns(['title', 'count(areas)'])
            ->params([])
            ->output(['filters' => ['areas_count' => ['op' => 'between', 'from' => 2, 'to' => 5]]]);

        $titles = collect($result['rows'])->pluck('title')->all();

        $this->assertSame(['mid'], $titles);
    }

    private function parseColumn($model, $field): array {
        $service = (new ListService($model))->columns([$field]);

        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('columns');
        $property->setAccessible(true);

        return $property->getValue($service)[0];
    }

    private function stubMenus(array $menus): void {
        $permission = $this->createStub(AdminPermission::class);
        $permission->method('getMenus')->willReturn($menus);
        $this->app->instance(AdminPermission::class, $permission);
    }

}
