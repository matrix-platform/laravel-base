<?php //>

namespace Tests\Feature\Database\Seeders;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Database\Seeders\CitySeeder;
use MatrixPlatform\Models\ManipulationLog;
use Tests\FeatureTestCase;

class CitySeederTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        (new CitySeeder())->run();
    }

    /**
     * @return array<int, string>
     */
    private function blocks(): array {
        $blocks = [];

        foreach (DB::table('base_city_area')->orderBy('post_code')->get(['city_id', 'post_code']) as $area) {
            $city = intval($area->city_id);

            if (!array_key_exists($city, $blocks)) {
                $blocks[$city] = strval($area->post_code);
            }
        }

        ksort($blocks);

        return $blocks;
    }

    public function test_the_seeder_writes_every_city_and_area(): void {
        $this->assertSame(23, DB::table('base_city')->count());
        $this->assertSame(371, DB::table('base_city_area')->count());
    }

    public function test_the_seeder_pins_the_identifiers_it_ships(): void {
        $this->assertSame([1, 23], [intval(DB::table('base_city')->min('id')), intval(DB::table('base_city')->max('id'))]);
        $this->assertSame([1000, 9830], [intval(DB::table('base_city_area')->min('id')), intval(DB::table('base_city_area')->max('id'))]);
        $this->assertSame(371, DB::table('base_city_area')->whereColumn('id', 'ranking')->count());
    }

    public function test_every_city_owns_the_expected_block_of_post_codes(): void {
        $this->assertSame([
            1 => '100', 2 => '200', 3 => '207', 4 => '260', 5 => '300', 6 => '302',
            7 => '320', 8 => '350', 9 => '400', 10 => '500', 11 => '540', 12 => '600',
            13 => '602', 14 => '630', 15 => '700', 16 => '800', 17 => '290', 18 => '880',
            19 => '900', 20 => '950', 21 => '970', 22 => '890', 23 => '209'
        ], $this->blocks());
    }

    public function test_the_seeder_traces_every_row_it_writes(): void {
        $this->assertSame(23, ManipulationLog::query()->where('data_type', 'base_city')->count());
        $this->assertSame(371, ManipulationLog::query()->where('data_type', 'base_city_area')->count());
    }

}
