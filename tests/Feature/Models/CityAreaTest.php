<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;

class CityAreaTest extends FeatureTestCase {

    private function area(City $city, string $title, string $postCode, int $ranking): CityArea {
        $area = new CityArea();

        $area->city_id = $city->id;
        $area->title__en = $title;
        $area->post_code = $postCode;
        $area->ranking = $ranking;

        $area->save();

        return $area;
    }

    private function city(string $title, int $ranking): City {
        $city = new City();

        $city->title__en = $title;
        $city->ranking = $ranking;

        $city->save();

        return $city;
    }

    public function test_the_table_carries_the_declared_columns(): void {
        $this->assertEqualsCanonicalizing(
            ['id', 'city_id', 'title__tw', 'title__en', 'post_code', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'],
            Schema::getColumnListing('base_city_area')
        );
    }

    public function test_the_relation_returns_the_owning_city(): void {
        $taipei = $this->city('Taipei', 100);
        $area = $this->area($taipei, 'Daan', '106', 100);
        $owner = $area->city;

        $this->assertNotNull($owner);
        $this->assertSame($taipei->id, $owner->id);
        $this->assertSame('Taipei', $owner->title__en);
    }

    public function test_the_declared_metadata_nests_an_area_under_its_city(): void {
        $metadata = app(MetadataRegistry::class)->of(CityArea::class);

        $this->assertNotNull($metadata);
        $this->assertSame('area', $metadata->alias);
        $this->assertSame('city', $metadata->parent);
    }

}
