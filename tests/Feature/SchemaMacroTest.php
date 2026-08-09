<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

class SchemaMacroTest extends FeatureTestCase {

    public function test_primary_key_draws_from_the_shared_sequence(): void {
        $first = Widget::forceCreate(['title' => 'alpha']);
        $second = Widget::forceCreate(['title' => 'beta']);

        $this->assertGreaterThanOrEqual(10000000, $first->getKey());
        $this->assertGreaterThan($first->getKey(), $second->getKey());
    }

    public function test_ranking_steps_by_one_hundred(): void {
        $first = Widget::forceCreate(['title' => 'alpha'])->refresh();
        $second = Widget::forceCreate(['title' => 'beta'])->refresh();

        $this->assertSame(100, $second->ranking - $first->ranking);
    }

    public function test_schedules_adds_both_window_columns(): void {
        $this->assertTrue(Schema::hasColumn('stub_widget', 'enable_time'));
        $this->assertTrue(Schema::hasColumn('stub_widget', 'disable_time'));
    }

    public function test_auditings_adds_all_four_columns_by_default(): void {
        $this->assertTrue(Schema::hasColumn('stub_widget', 'creator_id'));
        $this->assertTrue(Schema::hasColumn('stub_widget', 'create_time'));
        $this->assertTrue(Schema::hasColumn('stub_widget', 'updater_id'));
        $this->assertTrue(Schema::hasColumn('stub_widget', 'update_time'));
    }

    public function test_auditings_can_leave_out_the_update_columns(): void {
        $this->assertTrue(Schema::hasColumn('base_manipulation_log', 'creator_id'));
        $this->assertTrue(Schema::hasColumn('base_manipulation_log', 'create_time'));
        $this->assertFalse(Schema::hasColumn('base_manipulation_log', 'updater_id'));
        $this->assertFalse(Schema::hasColumn('base_manipulation_log', 'update_time'));
    }

}
