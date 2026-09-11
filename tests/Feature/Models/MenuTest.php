<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use Tests\FeatureTestCase;

class MenuTest extends FeatureTestCase {

    private function node(string $title): Menu {
        $menu = new Menu();

        $menu->title__en = $title;
        $menu->ranking = 0;

        $menu->save();

        return $menu;
    }

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

    public function test_deleting_a_leaf_node_succeeds(): void {
        $leaf = $this->node('leaf');

        (new DeleteService(Menu::class))->standalone(true)->delete(['id' => $leaf->id]);

        $this->assertNull(Menu::query()->find($leaf->id));
    }

    public function test_deleting_a_node_with_children_is_refused_instead_of_hitting_the_database_constraint(): void {
        $root = $this->node('root');

        $child = $this->node('child');

        $child->parent_id = $root->id;
        $child->save();

        try {
            (new DeleteService(Menu::class))->standalone(true)->delete(['id' => $root->id]);
        } catch (ServiceException $exception) {
            $this->assertSame('data-in-use', $exception->getError());
            $this->assertNotNull(Menu::query()->find($root->id));

            return;
        }

        $this->fail('the delete was expected to be refused');
    }

}
