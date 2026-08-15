<?php //>

namespace Tests\Feature\Columns\Query;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class FilteringTest extends FeatureTestCase {

    private const OPERATORS = [
        'eq', 'neq', 'contains', 'startsWith', 'endsWith',
        'gt', 'gte', 'lt', 'lte', 'between', 'in', 'notIn', 'null', 'notNull'
    ];

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     * @param array<string, mixed> $filters
     * @return list<mixed>
     */
    private function labels(array $columns, array $filters, ?Model $root = null): array {
        $plan = $this->plan($columns, $root);
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, $filters);

        return array_values($query->reorder()->orderBy('label')->pluck('label')->all());
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
     * @param array<string, mixed> $filters
     */
    private function sql(array $filters, ?string $column = null): string {
        $plan = $this->plan([$column === null ? ['name' => 'label', 'op' => self::OPERATORS] : $column]);
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, $filters);

        return $query->toSql();
    }

    private function trinkets(): void {
        Trinket::forceCreate(['label' => 'alpha', 'amount' => 10]);
        Trinket::forceCreate(['label' => 'Beta', 'amount' => 20]);
        Trinket::forceCreate(['label' => 'gamma', 'amount' => null]);
    }

    public function test_every_operator_compiles_into_a_where_clause(): void {
        $expected = [
            'eq' => '= ?',
            'neq' => '!= ?',
            'contains' => 'ILIKE ?',
            'startsWith' => 'ILIKE ?',
            'endsWith' => 'ILIKE ?',
            'gt' => '> ?',
            'gte' => '>= ?',
            'lt' => '< ?',
            'lte' => '<= ?',
            'in' => 'IN (?)',
            'notIn' => 'NOT IN (?)'
        ];

        foreach ($expected as $operator => $clause) {
            $sql = $this->sql(['label' => ['op' => $operator, 'value' => 'x']]);

            $this->assertStringContainsString("where \"stub_trinket\".\"label\" {$clause}", $sql, $operator);
        }

        $this->assertStringContainsString(
            'where "stub_trinket"."label" IS NULL',
            $this->sql(['label' => ['op' => 'null', 'value' => 'x']])
        );

        $this->assertStringContainsString(
            'where "stub_trinket"."label" IS NOT NULL',
            $this->sql(['label' => ['op' => 'notNull', 'value' => 'x']])
        );
    }

    public function test_between_covers_four_states(): void {
        $this->assertStringContainsString(
            'where "stub_trinket"."label" BETWEEN ? AND ?',
            $this->sql(['label' => ['op' => 'between', 'from' => 'a', 'to' => 'z']])
        );

        $this->assertStringContainsString(
            'where "stub_trinket"."label" >= ?',
            $this->sql(['label' => ['op' => 'between', 'from' => 'a']])
        );

        $this->assertStringContainsString(
            'where "stub_trinket"."label" <= ?',
            $this->sql(['label' => ['op' => 'between', 'to' => 'z']])
        );

        $this->assertStringNotContainsString('where', $this->sql(['label' => ['op' => 'between']]));
    }

    public function test_an_operator_outside_the_whitelist_is_ignored(): void {
        $plan = $this->plan([['name' => 'label', 'op' => 'eq']]);
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, ['label' => ['op' => 'contains', 'value' => 'a']]);

        $this->assertStringNotContainsString('where', $query->toSql());
    }

    public function test_a_column_without_an_operator_cannot_be_filtered(): void {
        $plan = $this->plan([['name' => 'label', 'op' => null]]);
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, ['label' => ['op' => 'eq', 'value' => 'a']]);

        $this->assertStringNotContainsString('where', $query->toSql());
    }

    public function test_a_null_value_turns_equality_into_a_null_test(): void {
        $this->assertStringContainsString(
            'where "stub_trinket"."label" IS NULL',
            $this->sql(['label' => ['op' => 'eq', 'value' => null]])
        );

        $this->assertStringContainsString(
            'where "stub_trinket"."label" IS NOT NULL',
            $this->sql(['label' => ['op' => 'neq', 'value' => null]])
        );
    }

    public function test_a_null_value_drops_the_operators_that_cannot_use_it(): void {
        $this->assertStringNotContainsString('where', $this->sql(['label' => ['op' => 'contains', 'value' => null]]));
    }

    public function test_a_null_value_on_in_yields_no_rows(): void {
        $this->trinkets();

        $this->assertSame([], $this->labels([['name' => 'label', 'op' => 'in']], ['label' => ['op' => 'in', 'value' => null]]));
    }

    public function test_a_malformed_filter_payload_is_ignored(): void {
        $this->assertStringNotContainsString('where', $this->sql(['label' => 'alpha']));

        $plan = $this->plan([['name' => 'label', 'op' => 'eq']]);
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, 'alpha');

        $this->assertStringNotContainsString('where', $query->toSql());
    }

    public function test_contains_is_case_insensitive(): void {
        $this->trinkets();

        $this->assertSame(
            ['Beta'],
            $this->labels([['name' => 'label', 'op' => 'contains']], ['label' => ['op' => 'contains', 'value' => 'bet']])
        );
    }

    public function test_wildcards_in_the_search_term_are_escaped(): void {
        Trinket::forceCreate(['label' => '100%']);
        Trinket::forceCreate(['label' => '100200']);

        $this->assertSame(
            ['100%'],
            $this->labels([['name' => 'label', 'op' => 'contains']], ['label' => ['op' => 'contains', 'value' => '100%']])
        );
    }

    public function test_in_does_not_quote_a_function_expression(): void {
        $this->trinkets();

        $this->assertSame(
            ['Beta'],
            $this->labels(
                [['name' => 'lowered=lower(label)', 'op' => 'in'], 'label'],
                ['lowered' => ['op' => 'in', 'value' => ['beta']]]
            )
        );
    }

    public function test_in_accepts_an_associative_value(): void {
        $this->trinkets();

        $this->assertSame(
            ['alpha', 'Beta'],
            $this->labels(
                [['name' => 'label', 'op' => 'in']],
                ['label' => ['op' => 'in', 'value' => ['first' => 'alpha', 'second' => 'Beta']]]
            )
        );
    }

    public function test_an_empty_in_yields_no_rows_and_an_empty_not_in_yields_all(): void {
        $this->trinkets();

        $columns = [['name' => 'label', 'op' => ['in', 'notIn']]];

        $this->assertSame([], $this->labels($columns, ['label' => ['op' => 'in', 'value' => []]]));
        $this->assertSame(['alpha', 'Beta', 'gamma'], $this->labels($columns, ['label' => ['op' => 'notIn', 'value' => []]]));
    }

    public function test_a_joined_column_can_be_filtered(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha']);

        Trinket::forceCreate(['label' => 'kept', 'widget_id' => $widget->id]);
        Trinket::forceCreate(['label' => 'dropped']);

        $this->assertSame(
            ['kept'],
            $this->labels(
                [['name' => 'widget.title', 'op' => 'eq'], 'label'],
                ['widget_title' => ['op' => 'eq', 'value' => 'Alpha']]
            )
        );
    }

    public function test_an_aggregated_column_can_be_filtered(): void {
        $alpha = Widget::forceCreate(['title' => 'Alpha']);
        $beta = Widget::forceCreate(['title' => 'Beta']);

        Trinket::forceCreate(['label' => 'a1', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'a2', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'b1', 'widget_id' => $beta->id]);

        $plan = $this->plan(['title', ['name' => 'count(trinkets)', 'op' => 'between']], new Widget());
        $query = $plan->projection();

        (new Filtering())->apply($query, $plan, ['trinkets_count' => ['op' => 'between', 'from' => 2, 'to' => 9]]);

        $this->assertSame(['Alpha'], $query->pluck('title')->all());
    }

}
