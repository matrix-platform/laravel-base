<?php //>

namespace Tests\Feature\Columns\Query;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class QueryPlanTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    private function assertRejects(array $columns, ?Model $root = null): void {
        try {
            $this->plan($columns, $root);
        } catch (ServiceException $exception) {
            $this->assertSame('invalid-column-expression', $exception->getError());

            return;
        }

        $this->fail('the plan was expected to be rejected');
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    private function plan(array $columns, ?Model $root = null): QueryPlan {
        $model = $root === null ? new Widget() : $root;
        $parser = new ColumnParser();
        $resolver = app(ColumnResolver::class);

        return new QueryPlan($model, array_map(fn ($column) => $resolver->resolve($parser->parse($column), $model), $columns));
    }

    private function widgets(): void {
        $alpha = Widget::forceCreate(['title' => 'Alpha']);
        $beta = Widget::forceCreate(['title' => 'Beta']);

        Widget::forceCreate(['title' => 'Gamma']);

        Trinket::forceCreate(['label' => 'a1', 'widget_id' => $alpha->id, 'amount' => 10]);
        Trinket::forceCreate(['label' => 'a2', 'widget_id' => $alpha->id, 'amount' => 30]);
        Trinket::forceCreate(['label' => 'b1', 'widget_id' => $beta->id, 'amount' => 5]);
    }

    public function test_a_root_column_needs_no_join(): void {
        $this->assertSame('select * from "stub_widget"', $this->plan(['title'])->complete()->toSql());
    }

    public function test_a_belongs_to_relation_becomes_a_left_join(): void {
        $sql = $this->plan(['widget.title'], new Trinket())
            ->projection()
            ->toSql();

        $this->assertSame(
            'select "stub_trinket"."id", widget.title as widget_title from "stub_trinket" '
                . 'left join "stub_widget" as "widget" on "widget"."id" = "stub_trinket"."widget_id"',
            $sql
        );
    }

    public function test_a_has_one_relation_joins_on_the_local_key(): void {
        $sql = $this->plan(['sole.label'], new Widget())
            ->projection()
            ->toSql();

        $this->assertStringContainsString(
            'left join "stub_trinket" as "sole" on "sole"."widget_id" = "stub_widget"."id"',
            $sql
        );
    }

    public function test_a_two_level_path_chains_the_joins(): void {
        $sql = $this->plan(['trinket.widget.title'], new Trinket())
            ->projection()
            ->toSql();

        $this->assertStringContainsString(
            'left join "stub_trinket" as "trinket" on "trinket"."id" = "stub_trinket"."trinket_id"',
            $sql
        );

        $this->assertStringContainsString(
            'left join "stub_widget" as "trinket__widget" on "trinket__widget"."id" = "trinket"."widget_id"',
            $sql
        );
    }

    public function test_a_relation_used_by_two_columns_is_joined_once(): void {
        $sql = $this->plan(['widget.title', 'widget.ranking'], new Trinket())
            ->projection()
            ->toSql();

        $this->assertSame(1, substr_count($sql, 'left join'));
    }

    public function test_projection_selects_the_key_and_every_declared_column(): void {
        $sql = $this->plan(['title', 'ranking'])
            ->projection()
            ->toSql();

        $this->assertSame(
            'select "stub_widget"."id", stub_widget.title as title, stub_widget.ranking as ranking from "stub_widget"',
            $sql
        );
    }

    public function test_complete_selects_the_whole_row_and_only_the_joined_columns(): void {
        $sql = $this->plan(['title', 'widget.title'], new Trinket())
            ->complete()
            ->toSql();

        $this->assertSame(
            'select "stub_trinket".*, widget.title as widget_title from "stub_trinket" '
                . 'left join "stub_widget" as "widget" on "widget"."id" = "stub_trinket"."widget_id"',
            $sql
        );
    }

    public function test_a_virtual_column_is_not_selected(): void {
        $sql = $this->plan(['title', '+note'])
            ->projection()
            ->toSql();

        $this->assertSame('select "stub_widget"."id", stub_widget.title as title from "stub_widget"', $sql);
    }

    public function test_a_virtual_column_keeps_its_join_so_that_it_stays_filterable(): void {
        $plan = $this->plan([['name' => '+widget.title', 'op' => 'eq']], new Trinket());

        $this->assertStringContainsString('left join "stub_widget" as "widget"', $plan->projection()->toSql());
        $this->assertSame('widget.title', $plan->field('widget_title'));
    }

    public function test_a_function_wraps_the_qualified_field(): void {
        $sql = $this->plan(['lowered=lower(title)'])
            ->projection()
            ->toSql();

        $this->assertSame(
            'select "stub_widget"."id", lower(stub_widget.title) as lowered from "stub_widget"',
            $sql
        );
    }

    public function test_a_count_aggregate_becomes_a_grouped_subquery(): void {
        $sql = $this->plan(['count(trinkets)'])
            ->projection()
            ->toSql();

        $this->assertSame(
            'select "stub_widget"."id", trinkets.trinkets_count as trinkets_count from "stub_widget" '
                . 'left join (select "trinkets"."widget_id", count(*) as trinkets_count '
                . 'from "stub_trinket" as "trinkets" group by "trinkets"."widget_id") as "trinkets" '
                . 'on "trinkets"."widget_id" = "stub_widget"."id"',
            $sql
        );
    }

    public function test_the_grouped_key_keeps_its_own_name(): void {
        $sql = $this->plan(['count(trinkets)'])
            ->projection()
            ->toSql();

        $this->assertStringNotContainsString('"trinkets"."widget_id" as', $sql);
    }

    public function test_a_count_aggregate_counts_the_related_rows(): void {
        $this->widgets();

        $rows = $this->plan(['title', 'count(trinkets)'])
            ->projection()
            ->orderBy('title')
            ->get();

        $this->assertSame([2, 1, null], $rows->pluck('trinkets_count')->all());
    }

    public function test_the_other_aggregates_use_the_qualified_column(): void {
        $sql = $this->plan(['sum(trinkets.amount)', 'avg(trinkets.amount)', 'max(trinkets.amount)', 'min(trinkets.amount)'])
            ->projection()
            ->toSql();

        foreach (['sum', 'avg', 'max', 'min'] as $aggregate) {
            $this->assertStringContainsString("{$aggregate}(trinkets.amount) as trinkets_{$aggregate}_amount", $sql);
        }
    }

    public function test_two_aggregates_share_one_subquery(): void {
        $sql = $this->plan(['count(trinkets)', 'sum(trinkets.amount)'])
            ->projection()
            ->toSql();

        $this->assertSame(1, substr_count($sql, 'left join ('));
        $this->assertStringContainsString('count(*) as trinkets_count, sum(trinkets.amount) as trinkets_sum_amount', $sql);
    }

    public function test_a_conditional_aggregate_uses_a_filter_clause(): void {
        $query = $this->plan(['count(trinkets[label^=a])'])->projection();

        $this->assertStringContainsString('count(*) FILTER (WHERE trinkets.label ILIKE ?) as trinkets_count', $query->toSql());
        $this->assertSame(['a%'], $query->getBindings());
    }

    public function test_a_conditional_aggregate_separates_no_match_from_no_rows(): void {
        $this->widgets();

        $rows = $this->plan(['title', 'count(trinkets[label^=a])'])
            ->projection()
            ->orderBy('title')
            ->get();

        $this->assertSame([2, 0, null], $rows->pluck('trinkets_count')->all());
    }

    public function test_a_conditional_aggregate_does_not_break_the_total_count(): void {
        $this->widgets();

        $this->assertSame(3, $this->plan(['title', 'count(trinkets[label^=a])'])->projection()->count());
    }

    public function test_the_three_condition_specials_compile_into_the_filter_clause(): void {
        $query = $this->plan(['count(trinkets[widget_id=null])', 'x=count(trinkets[widget_id!=null])', 'y=count(trinkets[amount=5,10])'])
            ->projection();

        $sql = $query->toSql();

        $this->assertStringContainsString('FILTER (WHERE trinkets.widget_id IS NULL)', $sql);
        $this->assertStringContainsString('FILTER (WHERE trinkets.widget_id IS NOT NULL)', $sql);
        $this->assertStringContainsString('FILTER (WHERE trinkets.amount IN (?,?))', $sql);
        $this->assertSame(['5', '10'], $query->getBindings());
    }

    public function test_conditions_on_two_levels_are_joined_with_and(): void {
        $query = $this->plan(['count(trinkets[label^=a].trinket[label^=b])'])->projection();

        $this->assertStringContainsString(
            'FILTER (WHERE trinkets.label ILIKE ? AND trinkets__trinket.label ILIKE ?)',
            $query->toSql()
        );

        $this->assertSame(['a%', 'b%'], $query->getBindings());
    }

    public function test_a_nested_aggregate_joins_the_intermediate_table_inside_the_subquery(): void {
        $sql = $this->plan(['count(trinkets.trinket)'])
            ->projection()
            ->toSql();

        $this->assertSame(
            'select "stub_widget"."id", trinkets__trinket.trinkets__trinket_count as trinkets__trinket_count '
                . 'from "stub_widget" left join (select "trinkets"."widget_id", count(*) as trinkets__trinket_count '
                . 'from "stub_trinket" as "trinkets__trinket" '
                . 'inner join "stub_trinket" as "trinkets" on "trinkets__trinket"."id" = "trinkets"."trinket_id" '
                . 'group by "trinkets"."widget_id") as "trinkets__trinket" '
                . 'on "trinkets__trinket"."widget_id" = "stub_widget"."id"',
            $sql
        );
    }

    public function test_an_intermediate_node_of_an_aggregate_is_not_joined_in_the_outer_query(): void {
        $sql = $this->plan(['count(trinkets.trinket)'])
            ->projection()
            ->toSql();

        $this->assertSame(1, substr_count($sql, 'left join'));
    }

    public function test_a_referenced_intermediate_with_an_aggregated_leaf_is_allowed(): void {
        $sql = $this->plan(['widget.title', 'count(widget.trinkets)'], new Trinket())
            ->projection()
            ->toSql();

        $this->assertSame(2, substr_count($sql, 'left join'));
    }

    public function test_an_aggregate_without_a_relation_is_rejected(): void {
        $this->assertRejects(['sum(ranking)']);
    }

    public function test_an_alias_that_is_both_joined_and_aggregated_is_rejected(): void {
        $this->assertRejects(['widget.title', 'count(widget)'], new Trinket());
    }

    public function test_the_rejection_does_not_depend_on_the_column_order(): void {
        $this->assertRejects(['count(widget)', 'widget.title'], new Trinket());
    }

    public function test_a_belongs_to_many_relation_is_rejected(): void {
        $this->assertRejects(['count(tagged)']);
    }

    public function test_a_morph_many_relation_is_rejected(): void {
        $this->assertRejects(['count(owned)']);
    }

    public function test_a_has_many_relation_cannot_be_traversed_by_a_plain_column(): void {
        $this->assertRejects(['trinkets.label']);
    }

    public function test_an_identifier_cannot_carry_sql_punctuation(): void {
        foreach (['ti"tle', "ti'tle", 'title;drop', 'title drop'] as $source) {
            $this->assertRejects([$source]);
        }
    }

    public function test_a_condition_value_is_unrestricted_but_always_bound(): void {
        $query = $this->plan(["count(trinkets[label=a b;c'])"])->projection();

        $this->assertStringContainsString('FILTER (WHERE trinkets.label = ?)', $query->toSql());
        $this->assertSame(["a b;c'"], $query->getBindings());
    }

}
