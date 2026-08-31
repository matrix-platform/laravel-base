<?php //>

namespace Tests\Feature\Columns;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\RelationOptions;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PermissionTree;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class ColumnResolverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('authority');

        $this->declare([]);

        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));
    }

    /**
     * @param array<string, Definition> $definitions
     */
    private function declare(array $definitions): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), $definitions));
    }

    /**
     * @param string|array<string, mixed> $column
     */
    private function resolve(string|array $column, ?Model $root = null): Column {
        return app(ColumnResolver::class)->resolve((new ColumnParser())->parse($column), $root === null ? new Widget() : $root);
    }

    public function test_a_declared_class_string_provider_is_resolved_to_an_instance(): void {
        $this->declare(['title' => Definition::json('permissions', [], PermissionTree::class)]);

        $column = $this->resolve('title');

        $this->assertInstanceOf(PermissionTree::class, $column->options);
        $this->assertSame('authority', $column->options->options()[0]->id);
    }

    public function test_a_column_with_no_cast_falls_back_to_text(): void {
        $this->assertSame(ColumnType::Text, $this->resolve('title')->type);
    }

    public function test_a_datetime_cast_becomes_a_datetime_column(): void {
        $this->assertSame(ColumnType::DateTime, $this->resolve('enable_time')->type);
    }

    public function test_a_hashed_cast_becomes_text_with_a_password_presentation(): void {
        $column = $this->resolve('password', new User());

        $this->assertSame(ColumnType::Text, $column->type);
        $this->assertSame(Presentation::Password, $column->presentation);
    }

    public function test_an_array_cast_becomes_json(): void {
        $model = new class extends Widget {
            /**
             * @return array<string, string>
             */
            protected function casts(): array {
                return ['content' => 'array'];
            }
        };

        app(MetadataRegistry::class)->register($model::class, new StubDeclaration(new Metadata('widget')));

        $this->assertSame(ColumnType::Json, $this->resolve('content', $model)->type);
    }

    public function test_a_declaration_wins_over_the_cast(): void {
        $this->declare(['title' => Definition::integer()]);

        $this->assertSame(ColumnType::Integer, $this->resolve('title')->type);
    }

    public function test_a_declaration_carries_presentation_and_rule(): void {
        $this->declare(['title' => Definition::text(Presentation::Password, ['max:50'])]);

        $column = $this->resolve('title');

        $this->assertSame(Presentation::Password, $column->presentation);
        $this->assertSame(['max:50'], $column->rule);
    }

    public function test_a_declaration_rule_can_be_deferred_to_a_closure(): void {
        $this->declare(['title' => Definition::text(null, fn (): array => ['max:' . cfg('admin.token-idle-minutes')])]);

        $this->assertSame(['max:30'], $this->resolve('title')->rule);
    }

    public function test_a_column_rule_overrides_the_declaration_rule(): void {
        $this->declare(['title' => Definition::text(null, ['max:50'])]);

        $this->assertSame(['numeric'], $this->resolve(['name' => 'title', 'rule' => ['numeric']])->rule);
    }

    public function test_a_column_rule_keeps_a_deferred_declaration_rule_from_running(): void {
        $this->declare(['title' => Definition::text(null, fn (): array => error('unreachable'))]);

        $this->assertSame(['numeric'], $this->resolve(['name' => 'title', 'rule' => ['numeric']])->rule);
    }

    public function test_the_dsl_overrides_the_declaration(): void {
        $this->declare(['title' => Definition::integer()]);

        $this->assertSame(ColumnType::Boolean, $this->resolve('title:boolean')->type);
    }

    public function test_view_flags_are_never_inherited_from_the_declaration(): void {
        $this->declare(['title' => Definition::text()]);

        $column = $this->resolve('title');

        $this->assertFalse($column->required);
        $this->assertFalse($column->readonly);
        $this->assertFalse($column->virtual);
    }

    public function test_an_aggregate_wins_over_the_declaration(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label'), [
            'ranking' => Definition::integer()
        ]));

        $this->assertSame(ColumnType::Float, $this->resolve('avg(trinkets.ranking)')->type);
    }

    public function test_an_expression_reads_the_declaration_of_the_terminal_model(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label'), [
            'ranking' => Definition::integer()
        ]));

        $this->assertSame(ColumnType::Integer, $this->resolve('trinkets.ranking')->type);
    }

    public function test_an_explicit_type_wins_over_everything(): void {
        $this->assertSame(ColumnType::Boolean, $this->resolve('enable_time:boolean')->type);
    }

    public function test_an_aggregate_decides_the_type_before_the_cast(): void {
        $this->assertSame(ColumnType::Float, $this->resolve('avg(trinkets.ranking)')->type);
        $this->assertSame(ColumnType::Integer, $this->resolve('sum(trinkets.ranking)')->type);
    }

    public function test_a_count_aggregate_is_an_integer_shown_as_a_count(): void {
        $column = $this->resolve('count(trinkets)');

        $this->assertSame(ColumnType::Integer, $column->type);
        $this->assertSame(Presentation::Count, $column->presentation);
    }

    public function test_a_relation_column_takes_its_type_from_the_terminal_model(): void {
        $this->assertSame(ColumnType::DateTime, $this->resolve('widget.enable_time', new Trinket())->type);
    }

    public function test_an_uncast_integer_column_needs_a_declaration(): void {
        $this->assertSame(ColumnType::Text, $this->resolve('ranking')->type);

        $this->declare(['ranking' => Definition::integer()]);

        $this->assertSame(ColumnType::Integer, $this->resolve('ranking')->type);
    }

    public function test_a_title_comes_from_the_model_bundle(): void {
        $this->assertSame('Widget Title', $this->resolve('title')->title);
    }

    public function test_a_title_falls_back_to_the_default_bundle(): void {
        $this->assertSame('Enable Time', $this->resolve('enable_time')->title);
    }

    public function test_a_title_falls_back_to_the_braced_name(): void {
        $this->assertSame('{secret}', $this->resolve('secret')->title);
    }

    public function test_a_title_is_looked_up_by_output_name(): void {
        $this->assertSame('Trinkets', $this->resolve('count(trinkets)')->title);
    }

    public function test_the_placeholder_and_remark_come_from_suffixed_keys(): void {
        $column = $this->resolve('title');

        $this->assertSame('Type a title', $column->placeholder);
        $this->assertSame('Shown in the list', $column->remark);
    }

    public function test_a_named_bundle_becomes_a_bundle_option_provider(): void {
        $this->assertInstanceOf(BundleOptions::class, $this->resolve('title:text:status')->options);
    }

    public function test_an_id_column_finds_its_relation(): void {
        $this->assertInstanceOf(RelationOptions::class, $this->resolve('widget_id', new Trinket())->options);
    }

    public function test_an_id_column_pointing_at_an_undeclared_model_is_refused(): void {
        $this->expectExceptionMessage('undeclared-model');

        $this->resolve('gadget_id', new Trinket());
    }

    public function test_a_column_on_an_undeclared_model_is_refused(): void {
        $this->expectExceptionMessage('undeclared-model');

        $this->resolve('title', new Gadget());
    }

    public function test_an_id_column_without_a_relation_has_no_options(): void {
        $this->assertNull($this->resolve('creator_id')->options);
    }

    public function test_a_column_with_options_is_presented_as_a_select(): void {
        $this->assertSame(Presentation::Select, $this->resolve('title:text:status')->presentation);
    }

    public function test_a_select_keeps_its_own_data_type(): void {
        $column = $this->resolve('widget_id:integer', new Trinket());

        $this->assertSame(ColumnType::Integer, $column->type);
        $this->assertSame(Presentation::Select, $column->presentation);
    }

    public function test_a_multi_select_is_not_downgraded_to_a_select(): void {
        $this->assertSame(Presentation::MultiSelect, $this->resolve('title:multi-select:status')->presentation);
    }

    public function test_a_hidden_column_resolves_nothing(): void {
        $column = $this->resolve('title:hidden');

        $this->assertSame('{title}', $column->title);
        $this->assertNull($column->placeholder);
        $this->assertNull($column->options);
        $this->assertNull($column->op);
        $this->assertFalse($column->sortable);
    }

    public function test_a_hidden_column_with_an_operator_resolves_everything(): void {
        $column = $this->resolve(['name' => 'title:hidden:status', 'op' => 'in']);

        $this->assertSame('Widget Title', $column->title);
        $this->assertInstanceOf(BundleOptions::class, $column->options);
        $this->assertSame(Presentation::Hidden, $column->presentation);
    }

    public function test_the_default_operator_follows_both_axes(): void {
        $this->assertSame('contains', $this->resolve('title')->op);
        $this->assertSame('between', $this->resolve('enable_time')->op);
        $this->assertSame('eq', $this->resolve('disabled', new User())->op);
        $this->assertSame('in', $this->resolve('title:text:status')->op);
    }

    public function test_an_explicit_null_operator_is_kept(): void {
        $this->assertNull($this->resolve(['name' => 'title', 'op' => null])->op);
    }

    public function test_an_explicit_null_sortable_is_recomputed(): void {
        $this->assertTrue($this->resolve(['name' => 'title', 'sortable' => null])->sortable);
    }

    public function test_a_boolean_column_is_not_sortable_by_default(): void {
        $this->assertFalse($this->resolve('disabled', new User())->sortable);
    }

    public function test_a_boolean_column_carries_yes_and_no_options(): void {
        $column = $this->resolve('disabled', new User());
        $options = $column->options === null ? [] : $column->options->options();

        $this->assertSame(Presentation::Select, $column->presentation);
        $this->assertSame([1, 0], array_map(fn (Option $option): int|string => $option->id, $options));
        $this->assertSame(['Yes', 'No'], array_map(fn (Option $option): string => $option->title, $options));
    }

    public function test_a_boolean_column_is_still_compared_by_equality(): void {
        $column = $this->resolve('disabled', new User());

        $this->assertNotNull($column->options);
        $this->assertSame('eq', $column->op);
    }

    public function test_an_explicit_option_provider_wins_over_the_boolean_default(): void {
        $options = new StaticOptions([]);
        $column = $this->resolve(['name' => 'disabled', 'options' => $options], new User());

        $this->assertSame($options, $column->options);
    }

    public function test_a_custom_widget_name_survives_resolution(): void {
        $this->assertSame('permissions', $this->resolve('title:permissions')->presentation);
    }

    public function test_a_caller_supplied_rule_is_carried_through(): void {
        $this->assertSame(['max:10'], $this->resolve(['name' => 'title', 'rule' => 'max:10'])->rule);
    }

}
