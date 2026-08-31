<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\CountingOptions;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class ExportServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        $this->declare([]);

        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
    }

    /**
     * @param array<string, Definition> $definitions
     */
    private function declare(array $definitions): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), $definitions));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     * @return array{title: string, columns: list<array<string, mixed>>, rows: list<array<string, string>>}
     */
    private function exported(array $columns, mixed $input = []): array {
        return (new ExportService(Widget::class))
            ->standalone(true)
            ->columns($columns)
            ->export($input);
    }

    /**
     * @param list<Option> $children
     */
    private function option(int|string $id, string $title, array $children = []): Option {
        return new Option($children, $id, 0, $title);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_the_output_carries_the_title_columns_and_rows(): void {
        $this->widget('Alpha');

        $exported = $this->exported(['title']);

        $this->assertSame('widget', $exported['title']);
        $this->assertSame(['title'], array_column($exported['columns'], 'name'));
        $this->assertSame([['title' => 'Alpha']], $exported['rows']);
    }

    public function test_a_column_entry_carries_the_type_and_the_presentation(): void {
        $this->widget('Alpha');

        $column = $this->exported(['enable_time'])['columns'][0];

        $this->assertSame('datetime', $column['type']);
        $this->assertSame('plain', $column['presentation']);
    }

    public function test_a_filter_column_is_not_part_of_the_output(): void {
        $widget = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'keep', 'widget_id' => $widget->id, 'amount' => 5]);
        Trinket::forceCreate(['label' => 'drop', 'widget_id' => $widget->id, 'amount' => 9]);

        $exported = (new ExportService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->filterColumns([['name' => 'amount', 'op' => 'eq']])
            ->export(['filters' => ['amount' => ['op' => 'eq', 'value' => 5]]]);

        $this->assertSame(['label'], array_column($exported['columns'], 'name'));
        $this->assertSame([['label' => 'keep']], $exported['rows']);
    }

    public function test_a_filter_column_can_be_sorted_by(): void {
        $widget = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'low', 'widget_id' => $widget->id, 'amount' => 1]);
        Trinket::forceCreate(['label' => 'high', 'widget_id' => $widget->id, 'amount' => 9]);

        $exported = (new ExportService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->filterColumns(['amount'])
            ->export(['sort' => [['name' => 'amount', 'direction' => 'desc']]]);

        $this->assertSame(['high', 'low'], array_column($exported['rows'], 'label'));
    }

    public function test_an_output_column_wins_over_a_filter_column_of_the_same_name(): void {
        $this->widget('Alpha');

        $columns = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['*title'])
            ->filterColumns(['title'])
            ->export([])['columns'];

        $this->assertCount(1, $columns);
        $this->assertSame('title', $columns[0]['name']);
    }

    public function test_a_joined_filter_column_registers_its_join(): void {
        $mine = $this->widget('Alpha');
        $other = $this->widget('Beta');

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $mine->id]);
        Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $other->id]);

        $exported = (new ExportService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->filterColumns(['widget.title'])
            ->export(['filters' => ['widget_title' => ['op' => 'contains', 'value' => 'Alph']]]);

        $this->assertSame([['label' => 'mine']], $exported['rows']);
    }

    public function test_a_null_value_becomes_an_empty_string(): void {
        Widget::forceCreate(['title' => null]);

        $this->assertSame([['title' => '']], $this->exported(['title'])['rows']);
    }

    public function test_a_boolean_column_becomes_a_translated_word(): void {
        $this->declare(['title' => Definition::boolean()]);

        Widget::forceCreate(['title' => '1']);
        Widget::forceCreate(['title' => '0']);

        $this->assertSame([['title' => 'Yes'], ['title' => 'No']], $this->exported(['title'])['rows']);
    }

    public function test_a_date_column_drops_the_time(): void {
        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $this->assertSame([['enable_time' => '2026-08-12']], $this->exported([['name' => 'enable_time', 'type' => 'date']])['rows']);
    }

    public function test_a_datetime_column_keeps_the_time(): void {
        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $this->assertSame([['enable_time' => '2026-08-12 13:45:07']], $this->exported(['enable_time'])['rows']);
    }

    public function test_a_date_column_honours_the_configured_format(): void {
        $this->useCfgFixtures();

        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $this->assertSame([['enable_time' => '12/08/2026']], $this->exported([['name' => 'enable_time', 'type' => 'date']])['rows']);
    }

    public function test_a_datetime_column_honours_the_configured_format(): void {
        $this->useCfgFixtures();

        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $this->assertSame([['enable_time' => '12/08/2026 13:45']], $this->exported(['enable_time'])['rows']);
    }

    public function test_a_real_boolean_value_becomes_a_translated_word(): void {
        $model = new class extends Widget {
            /**
             * @return array<string, string>
             */
            protected function casts(): array {
                return ['title' => 'boolean'];
            }
        };

        app(MetadataRegistry::class)->register($model::class, new StubDeclaration(new Metadata('widget')));

        Widget::forceCreate(['title' => '1']);
        Widget::forceCreate(['title' => '0']);

        $rows = (new ExportService($model::class))
            ->standalone(true)
            ->columns(['title'])
            ->export([])['rows'];

        $this->assertSame([['title' => 'Yes'], ['title' => 'No']], $rows);
    }

    public function test_a_select_presentation_wins_over_the_column_type(): void {
        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $options = new StaticOptions([$this->option('2026-08-12 13:45:07', 'Launch day')]);
        $rows = $this->exported([['name' => 'enable_time', 'options' => $options]])['rows'];

        $this->assertSame([['enable_time' => 'Launch day']], $rows);
    }

    public function test_an_unmatched_option_falls_back_to_the_raw_value(): void {
        $this->widget('Alpha');

        $options = new StaticOptions([$this->option('Beta', 'Second')]);
        $rows = $this->exported([['name' => 'title', 'options' => $options]])['rows'];

        $this->assertSame([['title' => 'Alpha']], $rows);
    }

    public function test_an_option_identifier_containing_a_dot_is_still_found(): void {
        $this->widget('a.b');

        $options = new StaticOptions([$this->option('a.b', 'Dotted')]);
        $rows = $this->exported([['name' => 'title', 'options' => $options]])['rows'];

        $this->assertSame([['title' => 'Dotted']], $rows);
    }

    public function test_a_multi_select_joins_the_option_titles(): void {
        Widget::forceCreate(['title' => '["a","b"]']);

        $options = new StaticOptions([$this->option('a', 'First'), $this->option('b', 'Second')]);
        $rows = $this->exported([['name' => 'title', 'type' => 'multi-select', 'options' => $options]])['rows'];

        $this->assertSame([['title' => 'First, Second']], $rows);
    }

    public function test_a_nested_option_is_part_of_the_lookup(): void {
        $this->widget('child');

        $options = new StaticOptions([$this->option('parent', 'Parent', [$this->option('child', 'Child')])]);
        $rows = $this->exported([['name' => 'title', 'options' => $options]])['rows'];

        $this->assertSame([['title' => 'Child']], $rows);
    }

    public function test_the_options_are_flattened_once_for_the_whole_export(): void {
        $this->widget('Alpha');
        $this->widget('Beta');
        $this->widget('Gamma');

        $options = new CountingOptions([$this->option('Alpha', 'First')]);

        $this->exported([['name' => 'title', 'options' => $options]]);

        $this->assertSame(1, $options->calls);
    }

    public function test_a_json_column_becomes_an_empty_string(): void {
        $model = new class extends Widget {
            /**
             * @return array<string, string>
             */
            protected function casts(): array {
                return ['title' => 'array'];
            }
        };

        app(MetadataRegistry::class)->register($model::class, new StubDeclaration(new Metadata('widget')));

        Widget::forceCreate(['title' => '["a","b"]']);

        $rows = (new ExportService($model::class))
            ->standalone(true)
            ->columns(['title'])
            ->export([])['rows'];

        $this->assertSame([['title' => '']], $rows);
    }

    public function test_a_hidden_presentation_is_left_out(): void {
        $this->widget('Alpha');

        $exported = $this->exported(['title', 'secret:hidden']);

        $this->assertSame(['title'], array_column($exported['columns'], 'name'));
        $this->assertSame([['title' => 'Alpha']], $exported['rows']);
    }

    public function test_a_password_presentation_is_left_out(): void {
        Widget::forceCreate(['title' => 'Alpha', 'ip' => '203.0.113.9']);

        $exported = $this->exported(['title', 'ip:password']);

        $this->assertSame(['title'], array_column($exported['columns'], 'name'));
        $this->assertSame([['title' => 'Alpha']], $exported['rows']);
    }

    public function test_a_column_hidden_on_the_model_is_left_out(): void {
        Widget::forceCreate(['title' => 'Alpha', 'secret' => 'classified']);

        $exported = $this->exported(['title', 'secret']);

        $this->assertSame(['title'], array_column($exported['columns'], 'name'));
        $this->assertSame([['title' => 'Alpha']], $exported['rows']);
    }

    public function test_an_aggregate_column_is_exported(): void {
        $widget = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'a', 'widget_id' => $widget->id]);
        Trinket::forceCreate(['label' => 'b', 'widget_id' => $widget->id]);

        $this->assertSame([['trinkets_count' => '2']], $this->exported(['count(trinkets)'])['rows']);
    }

    public function test_a_default_sorting_orders_the_rows(): void {
        Widget::forceCreate(['title' => 'Alpha', 'ranking' => 100]);
        Widget::forceCreate(['title' => 'Beta', 'ranking' => 200]);

        $rows = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->sorting(['-ranking'])
            ->export([])['rows'];

        $this->assertSame(['Beta', 'Alpha'], array_column($rows, 'title'));
    }

    public function test_a_nested_export_only_sees_its_own_parent(): void {
        $mine = $this->widget('Alpha');
        $other = $this->widget('Beta');

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $mine->id]);
        Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $other->id]);

        $rows = (new ExportService(Trinket::class))
            ->params(['widget_id' => $mine->id])
            ->columns(['label'])
            ->export([])['rows'];

        $this->assertSame([['label' => 'mine']], $rows);
    }

    public function test_a_missing_route_parameter_is_rejected(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('data-not-found');

        (new ExportService(Trinket::class))->columns(['label'])->export([]);
    }

    public function test_a_scope_narrows_the_export(): void {
        $this->widget('Alpha');
        $this->widget('Beta');

        $rows = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->scope(fn (Builder $query) => $query->where('title', 'Alpha'))
            ->export([])['rows'];

        $this->assertSame([['title' => 'Alpha']], $rows);
    }

    public function test_the_pagination_input_is_ignored(): void {
        $this->widget('Alpha');
        $this->widget('Beta');
        $this->widget('Gamma');

        $rows = $this->exported(['title'], ['page' => 1, 'size' => 1])['rows'];

        $this->assertCount(3, $rows);
    }

    public function test_a_guard_refuses_the_whole_export(): void {
        $this->widget('Alpha');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('permission-denied');

        (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard(fn () => error('permission-denied', 403))
            ->export([]);
    }

    public function test_a_guard_stops_before_the_later_rows_are_formatted(): void {
        $this->widget('Alpha');
        $this->widget('Beta');

        $formatted = 0;

        try {
            (new ExportService(Widget::class))
                ->standalone(true)
                ->columns(['title'])
                ->cell('title', function (mixed $raw) use (&$formatted): string {
                    $formatted++;

                    return strval($raw);
                })
                ->guard(fn (Widget $widget) => $widget->title === 'Beta' && error('permission-denied', 403))
                ->export([]);
        } catch (ServiceException) {
        }

        $this->assertSame(1, $formatted);
    }

    public function test_a_cell_callback_replaces_the_formatting(): void {
        $this->widget('Alpha');

        $rows = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title', 'enable_time'])
            ->cell('title', fn (mixed $raw, Widget $widget): string => strtoupper(strval($raw)) . ":{$widget->id}")
            ->export([])['rows'];

        $this->assertSame('ALPHA:' . Widget::query()->sole()->id, $rows[0]['title']);
        $this->assertSame('', $rows[0]['enable_time']);
    }

    public function test_the_last_cell_callback_for_a_name_wins(): void {
        $this->widget('Alpha');

        $rows = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->cell('title', fn (): string => 'first')
            ->cell('title', fn (): string => 'second')
            ->export([])['rows'];

        $this->assertSame([['title' => 'second']], $rows);
    }

    public function test_the_title_falls_back_to_the_alias_when_there_is_no_menu(): void {
        $this->widget('Alpha');

        $this->assertSame('widget', $this->exported(['title'])['title']);
    }

    public function test_translatable_export_defaults_to_the_current_locale(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $exported = $this->exported(['translated']);

        $this->assertSame(['translated'], array_column($exported['columns'], 'name'));
        $this->assertSame([['translated' => 'Beta']], $exported['rows']);
    }

    public function test_locales_expands_a_translatable_column_into_one_field_per_locale(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $exported = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->locales(['tw', 'en'])
            ->export([]);

        $this->assertSame(['translated__tw', 'translated__en'], array_column($exported['columns'], 'name'));
        $this->assertSame([['translated__tw' => 'Alpha', 'translated__en' => 'Beta']], $exported['rows']);
    }

    public function test_locales_can_expand_a_single_locale_only(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $exported = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->locales(['en'])
            ->export([]);

        $this->assertSame(['translated__en'], array_column($exported['columns'], 'name'));
        $this->assertSame([['translated__en' => 'Beta']], $exported['rows']);
    }

    public function test_a_cell_override_registered_once_applies_to_every_expanded_locale(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $rows = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->locales(['tw', 'en'])
            ->cell('translated', fn (mixed $raw): string => "[{$raw}]")
            ->export([])['rows'];

        $this->assertSame([['translated__tw' => '[Alpha]', 'translated__en' => '[Beta]']], $rows);
    }

}
