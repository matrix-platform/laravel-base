<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Database\Schema\BaseBlueprint;
use Tests\FeatureTestCase;
use Tests\Stubs\Widget;

class SchemaMacroTest extends FeatureTestCase {

    protected function tearDown(): void {
        Schema::dropIfExists('stub_translatable_probe');

        parent::tearDown();
    }

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

    public function test_translatable_adds_a_nullable_column_per_configured_locale(): void {
        Schema::create('stub_translatable_probe', function (BaseBlueprint $table) {
            $table->translatable('caption');
            $table->translatable('flag', 'boolean');
        });

        $this->assertTrue(Schema::hasColumn('stub_translatable_probe', 'caption__tw'));
        $this->assertTrue(Schema::hasColumn('stub_translatable_probe', 'caption__en'));
        $this->assertTrue(Schema::hasColumn('stub_translatable_probe', 'flag__tw'));
        $this->assertTrue(Schema::hasColumn('stub_translatable_probe', 'flag__en'));

        $types = DB::table('information_schema.columns')
            ->where('table_name', 'stub_translatable_probe')
            ->whereIn('column_name', ['caption__tw', 'flag__tw'])
            ->pluck('data_type', 'column_name');

        $this->assertSame('text', $types['caption__tw']);
        $this->assertSame('boolean', $types['flag__tw']);

        $nullable = DB::table('information_schema.columns')
            ->where('table_name', 'stub_translatable_probe')
            ->whereIn('column_name', ['caption__tw', 'caption__en', 'flag__tw', 'flag__en'])
            ->pluck('is_nullable', 'column_name');

        $this->assertSame(['YES', 'YES', 'YES', 'YES'], $nullable->values()->all());
    }

}
