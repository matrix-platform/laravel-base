<?php //>

namespace Tests\Feature\Support\Scaffold;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use Tests\FeatureTestCase;

class SchemaIntrospectorTest extends FeatureTestCase {

    public function test_columns_are_reported_in_physical_order_with_types_and_nullability(): void {
        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertSame(['id', 'parent_id', 'code', 'password', 'api_token', 'secret_key', 'weight', 'title', 'enable_time', 'disable_time', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'], array_keys($columns));
        $this->assertSame(ColumnType::Integer, $columns['id']->type);
        $this->assertFalse($columns['id']->nullable);
        $this->assertSame(ColumnType::Integer, $columns['parent_id']->type);
        $this->assertTrue($columns['parent_id']->nullable);
        $this->assertSame(ColumnType::Text, $columns['code']->type);
        $this->assertFalse($columns['code']->nullable);
        $this->assertSame(ColumnType::Float, $columns['weight']->type);
        $this->assertSame(ColumnType::DateTime, $columns['enable_time']->type);
        $this->assertSame(ColumnType::Integer, $columns['ranking']->type);
        $this->assertFalse($columns['create_time']->nullable);
        $this->assertTrue($columns['update_time']->nullable);
    }

    public function test_a_single_column_unique_constraint_is_detected(): void {
        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertTrue($columns['code']->unique);
        $this->assertFalse($columns['weight']->unique);
        $this->assertFalse($columns['parent_id']->unique);
    }

    public function test_a_composite_unique_constraint_is_skipped_but_reported_separately(): void {
        $introspector = new SchemaIntrospector();

        $this->assertCount(1, $introspector->compositeUniqueConstraints('scaffold_widget'));
        $this->assertSame([], $introspector->compositeUniqueConstraints('scaffold_simple'));
    }

    public function test_a_unique_constraint_sharing_its_name_with_another_tables_foreign_key_is_still_detected(): void {
        DB::statement('create table introspect_parent (id integer primary key)');
        DB::statement('create table introspect_unique (id integer primary key, email text, constraint introspect_shared unique (email))');
        DB::statement('create table introspect_child (id integer primary key, parent_id integer, constraint introspect_shared foreign key (parent_id) references introspect_parent (id))');

        $introspector = new SchemaIntrospector();
        $columns = $introspector->columns('introspect_unique');

        $this->assertTrue($columns['email']->unique);
        $this->assertSame([], $introspector->compositeUniqueConstraints('introspect_unique'));
        $this->assertSame('introspect_parent', $introspector->columns('introspect_child')['parent_id']->foreignTable);
    }

    public function test_a_single_column_foreign_key_and_its_referenced_table_are_detected(): void {
        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertSame('scaffold_parent', $columns['parent_id']->foreignTable);
        $this->assertNull($columns['code']->foreignTable);
    }

    public function test_a_foreign_key_sharing_its_name_with_another_table_is_still_detected(): void {
        DB::statement('create table introspect_parent (id integer primary key)');
        DB::statement('create table introspect_child_a (id integer primary key, parent_id integer, constraint introspect_shared_fk foreign key (parent_id) references introspect_parent (id))');
        DB::statement('create table introspect_child_b (id integer primary key, other_id integer, constraint introspect_shared_fk foreign key (other_id) references introspect_parent (id))');

        $introspector = new SchemaIntrospector();

        $this->assertSame('introspect_parent', $introspector->columns('introspect_child_a')['parent_id']->foreignTable);
        $this->assertSame('introspect_parent', $introspector->columns('introspect_child_b')['other_id']->foreignTable);
        $this->assertArrayNotHasKey('other_id', $introspector->columns('introspect_child_a'));
    }

    public function test_a_composite_foreign_key_is_skipped(): void {
        DB::statement('create table introspect_pair (a integer, b integer, primary key (a, b))');
        DB::statement('create table introspect_pair_child (id integer primary key, a integer, b integer, foreign key (a, b) references introspect_pair (a, b))');

        $columns = (new SchemaIntrospector())->columns('introspect_pair_child');

        $this->assertNull($columns['a']->foreignTable);
        $this->assertNull($columns['b']->foreignTable);
    }

    public function test_a_column_comment_is_reported_when_present(): void {
        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertSame('Weight in kilograms', $columns['weight']->comment);
        $this->assertNull($columns['code']->comment);
    }

    public function test_translatable_columns_converge_into_one_logical_field(): void {
        config(['matrix.locales' => 'tw en']);

        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayNotHasKey('title__tw', $columns);
        $this->assertTrue($columns['title']->translatable);
        $this->assertSame([], $columns['title']->missingLocales);
    }

    public function test_a_translatable_field_missing_some_locale_columns_is_still_converged(): void {
        config(['matrix.locales' => 'tw en jp']);

        $columns = (new SchemaIntrospector())->columns('scaffold_widget');

        $this->assertTrue($columns['title']->translatable);
        $this->assertSame(['jp'], $columns['title']->missingLocales);
    }

    public function test_table_existence_is_reported(): void {
        $introspector = new SchemaIntrospector();

        $this->assertTrue($introspector->tableExists('scaffold_widget'));
        $this->assertFalse($introspector->tableExists('no_such_table'));
    }

}
