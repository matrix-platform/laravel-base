<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use Tests\FeatureTestCase;

class CityTest extends FeatureTestCase {

    private function area(City $city, string $title, string $postCode, int $ranking): CityArea {
        $area = new CityArea();

        $area->city_id = $city->id;
        $area->title = $title;
        $area->post_code = $postCode;
        $area->ranking = $ranking;

        $area->save();

        return $area;
    }

    private function city(string $title, int $ranking): City {
        $city = new City();

        $city->title = $title;
        $city->ranking = $ranking;

        $city->save();

        return $city;
    }

    public function test_the_table_carries_the_declared_columns(): void {
        $this->assertSame(
            ['id', 'title', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'],
            Schema::getColumnListing('base_city')
        );
    }

    public function test_the_relation_returns_only_the_areas_of_that_city(): void {
        $taipei = $this->city('Taipei', 100);
        $kaohsiung = $this->city('Kaohsiung', 200);

        $this->area($taipei, 'Daan', '106', 100);
        $this->area($kaohsiung, 'Cijin', '805', 100);

        $this->assertSame(['Daan'], $taipei->areas()->pluck('title')->all());
    }

    public function test_the_relation_orders_the_areas_by_ranking(): void {
        $city = $this->city('Taipei', 100);

        $this->area($city, 'Daan', '106', 300);
        $this->area($city, 'Datong', '103', 200);
        $this->area($city, 'Zhongzheng', '100', 100);

        $this->assertSame(['Zhongzheng', 'Datong', 'Daan'], $city->areas()->pluck('title')->all());
    }

}
