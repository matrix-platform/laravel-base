<?php //>

namespace Tests\Unit\Columns\Syntax;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Columns\Syntax\ParsedColumn;
use MatrixPlatform\Exceptions\ServiceException;
use PHPUnit\Framework\TestCase;

class ColumnParserTest extends TestCase {

    /**
     * @param string|array<string, mixed> $column
     */
    private function parse(string|array $column): ParsedColumn {
        return (new ColumnParser())->parse($column);
    }

    private function refuses(string $column, string $slug): void {
        try {
            $this->parse($column);
        } catch (ServiceException $exception) {
            $this->assertSame($slug, $exception->getError(), $column);

            return;
        }

        $this->fail("expected '{$column}' to be refused with '{$slug}'");
    }

    public function test_a_bare_name_becomes_a_plain_column(): void {
        $column = $this->parse('title');

        $this->assertSame('title', $column->name);
        $this->assertSame('title', $column->expression->field);
        $this->assertSame([], $column->expression->path);
        $this->assertFalse($column->required);
        $this->assertFalse($column->readonly);
        $this->assertFalse($column->virtual);
    }

    public function test_the_star_prefix_marks_the_column_required(): void {
        $this->assertTrue($this->parse('*title')->required);
    }

    public function test_the_bang_prefix_marks_the_column_readonly(): void {
        $this->assertTrue($this->parse('!title')->readonly);
    }

    public function test_the_plus_prefix_marks_the_column_virtual_and_unsortable(): void {
        $column = $this->parse('+title');

        $this->assertTrue($column->virtual);
        $this->assertFalse($column->sortable);
    }

    public function test_stacked_prefixes_are_refused(): void {
        $this->refuses('*!title', 'invalid-column-expression');
        $this->refuses('!*title', 'invalid-column-expression');
        $this->refuses('+*title', 'invalid-column-expression');
    }

    public function test_the_hash_splits_off_a_group(): void {
        $this->assertSame('stats', $this->parse('title#stats')->group);
    }

    public function test_the_colon_splits_off_a_type(): void {
        $this->assertSame(ColumnType::Integer, $this->parse('amount:integer')->type);
    }

    public function test_the_hash_swallows_everything_after_it(): void {
        $column = $this->parse('total#stats:integer');

        $this->assertSame('stats:integer', $column->group);
        $this->assertNull($column->type);
    }

    public function test_the_canonical_order_carries_every_modifier(): void {
        $column = $this->parse('*total=sum(orders.amount):integer#stats');

        $this->assertTrue($column->required);
        $this->assertSame('total', $column->name);
        $this->assertSame('sum', $column->expression->aggregate);
        $this->assertSame(ColumnType::Integer, $column->type);
        $this->assertSame('stats', $column->group);
    }

    public function test_a_second_colon_names_an_options_bundle(): void {
        $column = $this->parse('state:text:status');

        $this->assertSame(ColumnType::Text, $column->type);
        $this->assertSame('status', $column->optionsName);
    }

    public function test_a_type_that_is_a_presentation_lands_on_the_other_axis(): void {
        $column = $this->parse('secret:password');

        $this->assertNull($column->type);
        $this->assertSame(Presentation::Password, $column->presentation);
    }

    public function test_an_unknown_type_is_kept_as_a_custom_widget_name(): void {
        $column = $this->parse('permissions:permissions');

        $this->assertNull($column->type);
        $this->assertSame('permissions', $column->presentation);
    }

    public function test_an_equals_sign_names_the_output_column(): void {
        $column = $this->parse('owner=group.title');

        $this->assertSame('owner', $column->name);
        $this->assertSame('title', $column->expression->field);
        $this->assertSame(['group'], $column->expression->path);
    }

    public function test_a_dotted_path_defaults_its_name_to_underscores(): void {
        $this->assertSame('group_title', $this->parse('group.title')->name);
    }

    public function test_a_plain_function_is_kept_without_becoming_an_aggregate(): void {
        $column = $this->parse('lower(group.title)');

        $this->assertSame('lower', $column->expression->function);
        $this->assertNull($column->expression->aggregate);
        $this->assertSame('group_title', $column->name);
    }

    public function test_the_five_aggregates_are_recognised(): void {
        foreach (['avg', 'count', 'max', 'min', 'sum'] as $aggregate) {
            $this->assertSame($aggregate, $this->parse("{$aggregate}(orders.amount)")->expression->aggregate, $aggregate);
        }
    }

    public function test_a_count_aggregate_keeps_its_last_segment_in_the_path(): void {
        $column = $this->parse('count(orders)');

        $this->assertNull($column->expression->field);
        $this->assertSame(['orders'], $column->expression->path);
        $this->assertSame('orders_count', $column->name);
    }

    public function test_an_aggregate_default_name_joins_alias_aggregate_and_field(): void {
        $this->assertSame('a__b_sum_amount', $this->parse('sum(a.b.amount)')->name);
        $this->assertSame('a__b_count', $this->parse('count(a.b)')->name);
    }

    public function test_bracket_conditions_are_keyed_by_join_alias(): void {
        $conditions = $this->parse('sum(a.b[status=1].amount)')->expression->conditions;

        $this->assertSame(['a__b'], array_keys($conditions));
        $this->assertSame('status', $conditions['a__b'][0]->field);
        $this->assertSame('=', $conditions['a__b'][0]->operator);
        $this->assertSame('1', $conditions['a__b'][0]->value);
    }

    public function test_a_condition_on_the_field_itself_lands_on_the_last_alias(): void {
        $conditions = $this->parse('sum(orders.amount[amount>0])')->expression->conditions;

        $this->assertSame(['orders'], array_keys($conditions));
        $this->assertSame('>', $conditions['orders'][0]->operator);
    }

    public function test_null_comparisons_become_null_operators(): void {
        $conditions = $this->parse('count(a[x=null][y!=null])')->expression->conditions;

        $this->assertSame('NULL', $conditions['a'][0]->operator);
        $this->assertNull($conditions['a'][0]->value);
        $this->assertSame('NOT NULL', $conditions['a'][1]->operator);
    }

    public function test_comma_separated_values_become_set_operators(): void {
        $conditions = $this->parse('count(a[x=1,2][y!=3,4])')->expression->conditions;

        $this->assertSame('IN', $conditions['a'][0]->operator);
        $this->assertSame(['1', '2'], $conditions['a'][0]->value);
        $this->assertSame('NOT IN', $conditions['a'][1]->operator);
    }

    public function test_every_comparison_operator_is_recognised(): void {
        foreach (['^=', '$=', '*=', '>=', '<=', '!=', '=', '>', '<'] as $operator) {
            $conditions = $this->parse("count(a[x{$operator}5])")->expression->conditions;

            $this->assertSame($operator, $conditions['a'][0]->operator, $operator);
        }
    }

    public function test_conditions_outside_an_aggregate_are_not_parsed(): void {
        $this->refuses('a[x=1].b', 'invalid-column-expression');
    }

    public function test_a_malformed_expression_is_refused(): void {
        $this->refuses('x..y', 'invalid-column-expression');
        $this->refuses('a-b', 'invalid-column-expression');
    }

    public function test_a_malformed_condition_is_refused(): void {
        $this->refuses('count(a[nonsense])', 'invalid-column-condition');
    }

    public function test_each_column_is_parsed_independently(): void {
        $this->parse('a.b');

        $this->refuses('x..y', 'invalid-column-expression');
    }

    public function test_the_array_form_carries_caller_overrides(): void {
        $column = $this->parse([
            'name' => 'title',
            'op' => 'contains',
            'rule' => 'max:10',
            'path' => 'widget/{id}',
            'title' => 'Given',
            'placeholder' => 'Hint',
            'remark' => 'Note',
            'sortable' => false
        ]);

        $this->assertSame('contains', $column->op);
        $this->assertTrue($column->opGiven);
        $this->assertSame(['max:10'], $column->rule);
        $this->assertSame('widget/{id}', $column->path);
        $this->assertSame('Given', $column->title);
        $this->assertSame('Hint', $column->placeholder);
        $this->assertSame('Note', $column->remark);
        $this->assertFalse($column->sortable);
    }

    public function test_an_explicit_null_operator_is_distinguishable_from_an_absent_one(): void {
        $given = $this->parse(['name' => 'title', 'op' => null]);
        $absent = $this->parse(['name' => 'title']);

        $this->assertTrue($given->opGiven);
        $this->assertNull($given->op);
        $this->assertFalse($absent->opGiven);
    }

    public function test_an_operator_may_be_a_list(): void {
        $this->assertSame(['eq', 'in'], $this->parse(['name' => 'title', 'op' => ['eq', 'in']])->op);
    }

    public function test_an_absent_sortable_stays_undecided(): void {
        $this->assertNull($this->parse('title')->sortable);
    }

}
