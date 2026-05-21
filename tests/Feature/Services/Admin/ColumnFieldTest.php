<?php //>

namespace Tests\Feature\Services\Admin;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\Common\ListService;
use MatrixPlatform\Support\AdminPermission;
use ReflectionClass;
use Tests\FeatureTestCase;

class ColumnFieldTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $permission = $this->createStub(AdminPermission::class);
        $permission->method('getMenus')->willReturn([]);
        $this->app->instance(AdminPermission::class, $permission);
    }

    public function test_flat_column_field_is_qualified_with_table() {
        $service = (new ListService(User::class))->columns(['username']);

        $column = $this->columns($service)[0];

        $this->assertSame('base_user.username', $column['$field']);
        $this->assertSame('username', $column['name']);
        $this->assertArrayNotHasKey('$join', $column);
        $this->assertArrayNotHasKey('$alias', $column);
        $this->assertArrayNotHasKey('$raw', $column);
    }

    public function test_cross_table_column_marked_as_join() {
        $service = (new ListService(User::class))->columns(['group.title']);

        $column = $this->columns($service)[0];

        $this->assertSame('group.title', $column['$field']);
        $this->assertSame('group_title', $column['name']);
        $this->assertTrue($column['$join']);
        $this->assertArrayNotHasKey('$alias', $column);
        $this->assertArrayNotHasKey('$raw', $column);
    }

    public function test_agg_column_field_references_subquery_output() {
        $service = (new ListService(City::class))->columns(['count(areas)']);

        $column = $this->columns($service)[0];

        $this->assertSame('areas.areas_count', $column['$field']);
        $this->assertSame('count(*)', $column['$raw']);
        $this->assertArrayNotHasKey('$subCol', $column);
        $this->assertSame('areas', $column['$alias']);
        $this->assertTrue($column['$join']);
        $this->assertSame('areas_count', $column['name']);
        $this->assertSame('count', $column['type']);
    }

    public function test_agg_with_field_uses_subquery_col() {
        $service = (new ListService(City::class))->columns(['max(areas.id)']);

        $column = $this->columns($service)[0];

        $this->assertSame('areas.areas_max_id', $column['$field']);
        $this->assertSame('max(id)', $column['$raw']);
        $this->assertArrayNotHasKey('$subCol', $column);
        $this->assertSame('areas', $column['$alias']);
        $this->assertSame('areas_max_id', $column['name']);
    }

    public function test_agg_avg_infers_float_type() {
        $service = (new ListService(City::class))->columns(['avg(areas.id)']);

        $column = $this->columns($service)[0];

        $this->assertSame('float', $column['type']);
    }

    public function test_scalar_on_local_column() {
        $service = (new ListService(User::class))->columns(['upper(username)']);

        $column = $this->columns($service)[0];

        $this->assertSame('upper(base_user.username)', $column['$field']);
        $this->assertSame('username', $column['name']);
        $this->assertArrayNotHasKey('$join', $column);
        $this->assertArrayNotHasKey('$alias', $column);
        $this->assertArrayNotHasKey('$raw', $column);
    }

    public function test_scalar_on_cross_table_column() {
        $service = (new ListService(User::class))->columns(['upper(group.title)']);

        $column = $this->columns($service)[0];

        $this->assertSame('upper(group.title)', $column['$field']);
        $this->assertSame('group_title', $column['name']);
        $this->assertTrue($column['$join']);
    }

    public function test_unknown_function_treated_as_scalar() {
        $service = (new ListService(User::class))->columns(['foo_bar(username)']);

        $column = $this->columns($service)[0];

        $this->assertSame('foo_bar(base_user.username)', $column['$field']);
        $this->assertSame('username', $column['name']);
    }

    public function test_agg_multiple_columns_merge_into_single_joinsub() {
        $service = (new ListService(City::class))->columns(['count(areas)', 'max(areas.id)']);

        $joins = $this->joins($service);

        $this->assertCount(1, $joins);
        $this->assertArrayHasKey('areas', $joins);
        $this->assertTrue($joins['areas']['agg']);
    }

    public function test_alias_on_cross_table_column() {
        $service = (new ListService(User::class))->columns(['label=group.name']);

        $column = $this->columns($service)[0];

        $this->assertSame('group.name', $column['$field']);
        $this->assertSame('label', $column['name']);
        $this->assertTrue($column['$join']);
    }

    public function test_alias_on_agg_column() {
        $service = (new ListService(City::class))->columns(['total=count(areas)']);

        $column = $this->columns($service)[0];

        $this->assertSame('areas.total', $column['$field']);
        $this->assertSame('total', $column['name']);
        $this->assertSame('count(*)', $column['$raw']);
    }

    public function test_alias_with_type_override_on_count_is_ignored() {
        $service = (new ListService(City::class))->columns(['total=count(areas):integer']);

        $column = $this->columns($service)[0];

        $this->assertSame('total', $column['name']);
        $this->assertSame('count', $column['type']);
    }

    private function columns(ListService $service): array {
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('columns');
        $property->setAccessible(true);

        return $property->getValue($service);
    }

    private function joins(ListService $service): array {
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('joins');
        $property->setAccessible(true);

        return $property->getValue($service);
    }

}
