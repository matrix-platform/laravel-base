<?php //>

namespace Tests\Feature\Services;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Database\Seeders\CitySeeder;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Services\CommonService;
use Tests\FeatureTestCase;

class CommonServiceTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(CitySeeder::class);
    }

    private function service() {
        return app(CommonService::class);
    }

    public function test_city_returns_all_cities_ordered_by_ranking() {
        $cities = $this->service()->city();

        $this->assertGreaterThan(0, $cities->count());

        $rankings = $cities->pluck('ranking')->all();
        $sorted = $rankings;
        sort($sorted);
        $this->assertEquals($sorted, $rankings);
    }

    public function test_city_eager_loads_areas_with_post_code() {
        $cities = $this->service()->city();

        $withAreas = $cities->first(fn ($c) => $c->areas->isNotEmpty());
        $this->assertNotNull($withAreas);
        $this->assertArrayHasKey('post_code', $withAreas->areas->first()->toArray());
    }

    public function test_menu_returns_active_menus_for_parent_id() {
        DB::table('base_menu')->insert([
            'parent_id' => null,
            'title' => '首頁',
            'enable_time' => now()->subHour(),
            'disable_time' => null
        ]);

        $menus = $this->service()->menu(null);

        $this->assertCount(1, $menus);
        $this->assertEquals('首頁', $menus->first()->title);
    }

    public function test_menu_excludes_inactive_menus() {
        DB::table('base_menu')->insert([
            'parent_id' => null,
            'title' => '未來選單',
            'enable_time' => now()->addHour(),
            'disable_time' => null
        ]);

        $menus = $this->service()->menu(null);

        $this->assertCount(0, $menus);
    }

}
