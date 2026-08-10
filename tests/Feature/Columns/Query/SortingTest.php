<?php //>

namespace Tests\Feature\Columns\Query;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Query\Direction;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Query\Sorting;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class SortingTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    private function plan(array $columns, ?Model $root = null): QueryPlan {
        $model = $root === null ? new Trinket() : $root;
        $parser = new ColumnParser();
        $resolver = app(ColumnResolver::class);

        return new QueryPlan($model, array_map(fn ($column) => $resolver->resolve($parser->parse($column), $model), $columns));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     * @param list<string> $defaults
     */
    private function sql(array $columns, mixed $requested, array $defaults = []): string {
        $plan = $this->plan($columns);
        $query = $plan->projection();

        (new Sorting($defaults))->apply($query, $plan, $requested);

        return $query->toSql();
    }

    public function test_a_sortable_column_is_ordered_by_its_expression(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => 'desc']]);

        $this->assertStringContainsString('order by stub_trinket.label desc', $sql);
    }

    public function test_an_unsortable_column_is_ignored(): void {
        $sql = $this->sql([['name' => 'label', 'sortable' => false]], [['name' => 'label', 'direction' => 'asc']]);

        $this->assertStringNotContainsString('order by', $sql);
    }

    public function test_an_unknown_column_is_ignored(): void {
        $this->assertStringNotContainsString('order by', $this->sql(['label'], [['name' => 'nothing']]));
    }

    public function test_a_missing_direction_defaults_to_ascending(): void {
        $this->assertStringContainsString('order by stub_trinket.label asc', $this->sql(['label'], [['name' => 'label']]));
    }

    public function test_an_explicit_null_direction_drops_the_whole_entry(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => null]]);

        $this->assertStringNotContainsString('order by', $sql);
    }

    public function test_the_direction_is_case_insensitive(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => 'DESC']]);

        $this->assertStringContainsString('order by stub_trinket.label desc', $sql);
    }

    public function test_an_unknown_direction_is_ignored(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => 'sideways']]);

        $this->assertStringNotContainsString('order by', $sql);
    }

    public function test_the_applied_list_reports_what_took_effect(): void {
        $plan = $this->plan(['label', ['name' => 'amount', 'sortable' => false]]);
        $query = $plan->projection();

        $applied = (new Sorting())->apply($query, $plan, [
            ['name' => 'label', 'direction' => 'desc'],
            ['name' => 'amount', 'direction' => 'asc']
        ]);

        $this->assertCount(1, $applied);
        $this->assertSame('label', $applied[0]->name);
        $this->assertSame(Direction::Desc, $applied[0]->direction);
    }

    public function test_the_defaults_never_appear_in_the_applied_list(): void {
        $plan = $this->plan(['label']);
        $query = $plan->projection();

        $applied = (new Sorting(['-ranking']))->apply($query, $plan, []);

        $this->assertSame([], $applied);
        $this->assertStringContainsString('order by stub_trinket.ranking desc', $query->toSql());
    }

    public function test_a_default_runs_after_the_requested_sorting(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => 'asc']], ['-ranking']);

        $this->assertStringContainsString('order by stub_trinket.label asc, stub_trinket.ranking desc', $sql);
    }

    public function test_a_default_is_skipped_when_the_column_was_already_requested(): void {
        $sql = $this->sql(['label'], [['name' => 'label', 'direction' => 'asc']], ['-label']);

        $this->assertStringContainsString('order by stub_trinket.label asc', $sql);
        $this->assertStringNotContainsString('desc', $sql);
    }

    public function test_a_default_on_an_unsortable_column_falls_back_to_the_root_table(): void {
        $sql = $this->sql([['name' => 'ranking', 'sortable' => false]], [], ['-ranking']);

        $this->assertStringContainsString('order by stub_trinket.ranking desc', $sql);
    }

    public function test_a_default_that_is_not_a_bare_name_is_dropped(): void {
        $this->assertStringNotContainsString('order by', $this->sql(['label'], [], ['-widget.title']));
    }

    public function test_an_aggregated_column_can_be_sorted(): void {
        $alpha = Widget::forceCreate(['title' => 'Alpha']);
        $beta = Widget::forceCreate(['title' => 'Beta']);

        Trinket::forceCreate(['label' => 'a1', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'a2', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'b1', 'widget_id' => $beta->id]);

        $plan = $this->plan(['title', 'count(trinkets)'], new Widget());
        $query = $plan->projection();

        (new Sorting())->apply($query, $plan, [['name' => 'trinkets_count', 'direction' => 'desc']]);

        $this->assertSame(['Alpha', 'Beta'], $query->pluck('title')->all());
    }

}
