<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\Menu;
use Tests\FeatureTestCase;

class MenuTest extends FeatureTestCase {

    public function test_the_table_carries_the_declared_columns(): void {
        $this->assertEqualsCanonicalizing(
            ['id', 'parent_id', 'title__tw', 'title__en', 'data', 'enable_time', 'disable_time', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'],
            Schema::getColumnListing('base_menu')
        );
    }

    public function test_the_data_column_is_stored_as_jsonb(): void {
        $this->assertSame('jsonb', Schema::getColumnType('base_menu', 'data'));
    }

    public function test_the_data_column_reads_back_as_an_array(): void {
        $menu = new Menu();

        $menu->title__en = 'dashboard';
        $menu->data = ['icon' => 'star', 'badge' => 3];
        $menu->ranking = 100;

        $menu->save();

        $data = Menu::query()->firstOrFail()->data;

        $this->assertNotNull($data);
        $this->assertSame(3, array_get_value($data, 'badge'));
        $this->assertSame('star', array_get_value($data, 'icon'));
    }

    public function test_the_schedule_columns_read_back_as_dates(): void {
        $created = new Menu();

        $created->title__en = 'dashboard';
        $created->enable_time = Carbon::parse('2026-01-02 03:04:05');
        $created->disable_time = Carbon::parse('2026-02-03 04:05:06');
        $created->ranking = 100;

        $created->save();

        $menu = Menu::query()->firstOrFail();

        $this->assertInstanceOf(Carbon::class, $menu->enable_time);
        $this->assertInstanceOf(Carbon::class, $menu->disable_time);
        $this->assertSame('2026-01-02 03:04:05', $menu->enable_time->format('Y-m-d H:i:s'));
    }

}
