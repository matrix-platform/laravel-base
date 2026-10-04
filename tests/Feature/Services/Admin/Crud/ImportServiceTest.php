<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Services\Admin\Crud\ImportService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class ImportServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        $this->declare([]);

        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget'), [
            'amount' => Definition::integer(unique: true)
        ]));
    }

    /**
     * @param array<string, Definition> $definitions
     */
    private function declare(array $definitions): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), $definitions));
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array{row: int, fields: array<string, list<string>>}
     */
    private function failure(int $row, array $fields): array {
        return ['row' => $row, 'fields' => $fields];
    }

    /**
     * @param list<array{row: int, fields: array<string, list<string>>}> $expected
     */
    private function assertFailed(array $expected, ImportService $service, mixed $input): void {
        try {
            $service->import($input);
        } catch (ServiceException $exception) {
            $this->assertSame('import-failed', $exception->getError());
            $this->assertSame(422, $exception->getCode());
            $this->assertSame($expected, $exception->getExtra()['rows']);

            return;
        }

        $this->fail('The import was expected to fail.');
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    private function importer(array $columns): ImportService {
        return (new ImportService(Widget::class))->standalone(true)->columns($columns);
    }

    /**
     * @param list<array<string, mixed>> $values
     * @return array{rows: list<array{row: int, values: array<string, mixed>}>}
     */
    private function input(array $values): array {
        return ['rows' => array_map(fn (array $item, int $index): array => ['row' => $index + 2, 'values' => $item], $values, array_keys($values))];
    }

    /**
     * @param list<Option> $children
     */
    private function option(int|string $id, string $title, array $children = []): Option {
        return new Option($children, $id, 0, $title);
    }

    /**
     * Finds the record by the raw `key` cell, rejecting the row when the narrowed query has no such record.
     */
    private function located(ImportService $service): ImportService {
        return $service->locate(function (Builder $query, array $values): Model {
            $found = $query->find(intval(array_get_value($values, 'key')));

            if (!$found instanceof Model) {
                invalid('key', 'exists');
            }

            return $found;
        });
    }

    private function trinkets(Widget $widget): ImportService {
        return (new ImportService(Trinket::class))->params(['widget_id' => $widget->id])->columns(['label', 'widget_id', 'amount']);
    }

    public function test_the_template_carries_the_title_the_importable_columns_and_no_rows(): void {
        $template = $this->importer(['*title', 'enable_time'])->template();

        $this->assertSame('widget', $template['title']);
        $this->assertSame(['title', 'enable_time'], array_column($template['columns'], 'name'));
        $this->assertSame([true, false], array_column($template['columns'], 'required'));
        $this->assertSame([], $template['rows']);
    }

    public function test_the_template_leaves_out_columns_that_cannot_be_imported(): void {
        $template = $this->importer(['id', 'title', '!ip', '+secret', 'pinned.label', 'payload', 'relic_id:hidden', 'trinket_id:multi-select', 'gallery:drive-image', 'secret:password'])->template();

        $this->assertSame(['title'], array_column($template['columns'], 'name'));
    }

    public function test_the_template_expands_a_translatable_column_into_every_locale(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        $template = $this->importer(['translated'])->template();

        $this->assertSame(['translated__tw', 'translated__en'], array_column($template['columns'], 'name'));
        $this->assertSame(['{translated} (繁體中文)', '{translated} (English)'], array_column($template['columns'], 'title'));
    }

    public function test_the_template_leaves_out_the_foreign_key_of_a_nested_resource(): void {
        $template = $this->trinkets(Widget::forceCreate(['title' => 'Owner']))->template();

        $this->assertSame(['label', 'amount'], array_column($template['columns'], 'name'));
    }

    public function test_the_template_lists_the_selectable_option_titles(): void {
        $options = new StaticOptions([
            $this->option(0, 'Group', [$this->option(1, 'Alpha'), $this->option(2, 'Beta')]),
            $this->option(3, 'Alpha'),
            new Option([], 9, 0, 'Hidden', selectable: false)
        ]);

        $template = $this->importer([['name' => 'title', 'options' => $options], 'enable_time'])->template();

        $this->assertSame(['Group', 'Alpha', 'Beta'], $template['columns'][0]['options']);
        $this->assertNull($template['columns'][1]['options']);
    }

    public function test_a_column_with_a_lookup_offers_no_options(): void {
        $options = new StaticOptions([$this->option(1, 'Alpha')]);

        $template = $this->importer([['name' => 'title', 'options' => $options]])
            ->lookup('title', fn (string $text): string => $text)
            ->template();

        $this->assertNull($template['columns'][0]['options']);
    }

    public function test_a_required_column_that_cannot_be_imported_refuses_the_import(): void {
        try {
            $this->importer(['title', '*payload'])->template();
        } catch (ServiceException $exception) {
            $this->assertSame('import-column-unsupported', $exception->getError());
            $this->assertSame(['payload'], $exception->getExtra()['columns']);

            return;
        }

        $this->fail('The template was expected to be refused.');
    }

    public function test_an_optional_column_that_cannot_be_imported_is_neither_validated_nor_written(): void {
        $this->importer(['title', 'payload'])->import($this->input([['title' => 'Alpha', 'payload' => 'ignored']]));

        $widget = Widget::query()->sole();

        $this->assertSame('Alpha', $widget->title);
        $this->assertNull($widget->payload);
    }

    public function test_a_required_foreign_key_of_a_nested_resource_does_not_refuse_the_import(): void {
        $widget = Widget::forceCreate(['title' => 'Owner']);

        (new ImportService(Trinket::class))
            ->params(['widget_id' => $widget->id])
            ->columns(['label', '*widget_id'])
            ->import($this->input([['label' => 'Alpha', 'widget_id' => '999']]));

        $this->assertSame($widget->id, Trinket::query()->sole()->widget_id);
    }

    public function test_the_rows_are_inserted_and_counted(): void {
        $result = $this->importer(['title', 'enable_time'])->import($this->input([
            ['title' => 'Alpha', 'enable_time' => '2026-01-02 03:04:05'],
            ['title' => ' Beta ', 'enable_time' => '']
        ]));

        $this->assertSame(['count' => 2], $result);
        $this->assertSame(['Alpha', 'Beta'], Widget::query()->orderBy('id')->pluck('title')->all());
        $this->assertSame('2026-01-02 03:04:05', Widget::query()->orderBy('id')->first()?->enable_time?->format('Y-m-d H:i:s'));
    }

    public function test_every_locale_of_a_translatable_column_is_written(): void {
        $this->declare(['translated' => Definition::text(translatable: true)]);

        $this->importer(['translated'])->import($this->input([['translated__tw' => '甲', 'translated__en' => 'A']]));

        $widget = Widget::query()->sole();

        $this->assertSame('甲', $widget->translated__tw);
        $this->assertSame('A', $widget->translated__en);
    }

    public function test_a_nested_import_takes_the_foreign_key_from_the_route(): void {
        $widget = Widget::forceCreate(['title' => 'Owner']);

        $this->trinkets($widget)->import($this->input([['label' => 'Alpha'], ['label' => 'Beta']]));

        $this->assertSame([$widget->id, $widget->id], Trinket::query()->pluck('widget_id')->all());
    }

    public function test_an_option_title_becomes_its_id(): void {
        $options = new StaticOptions([$this->option('draft', 'Draft'), $this->option('published', 'Published')]);

        $this->importer([['name' => 'title', 'options' => $options]])->import($this->input([['title' => 'Published']]));

        $this->assertSame('published', Widget::query()->sole()->title);
    }

    public function test_an_unknown_option_title_is_refused(): void {
        $options = new StaticOptions([$this->option('draft', 'Draft')]);

        $this->assertFailed([$this->failure(2, ['title' => ['in']])], $this->importer([['name' => 'title', 'options' => $options]]), $this->input([['title' => 'Nonsense']]));
    }

    public function test_an_ambiguous_option_title_is_refused(): void {
        $options = new StaticOptions([
            $this->option('home', 'Home', [$this->option('home-other', 'Other')]),
            $this->option('abroad', 'Abroad', [$this->option('abroad-other', 'Other')])
        ]);

        $this->assertFailed([$this->failure(2, ['title' => ['in']])], $this->importer([['name' => 'title', 'options' => $options]]), $this->input([['title' => 'Other']]));
    }

    public function test_an_option_that_is_not_selectable_is_refused(): void {
        $options = new StaticOptions([new Option([$this->option('city', 'City')], 'country', 0, 'Country', selectable: false)]);

        $this->assertFailed([$this->failure(2, ['title' => ['in']])], $this->importer([['name' => 'title', 'options' => $options]]), $this->input([['title' => 'Country']]));
    }

    public function test_a_numeric_option_id_is_written_as_text_into_a_text_column(): void {
        $options = new StaticOptions([$this->option(7, 'Seven')]);

        $this->importer([['name' => 'title', 'options' => $options]])->import($this->input([['title' => 'Seven']]));

        $this->assertSame('7', Widget::query()->sole()->title);
    }

    public function test_a_boolean_column_takes_the_option_titles_and_a_blank_is_false(): void {
        $this->declare(['flag' => Definition::boolean()]);

        $this->importer(['title', 'flag'])->import($this->input([
            ['title' => 'yes', 'flag' => 'Yes'],
            ['title' => 'no', 'flag' => 'No'],
            ['title' => 'blank', 'flag' => '']
        ]));

        $this->assertSame([true, false, false], Widget::query()->orderBy('id')->pluck('flag')->map(fn (mixed $flag): bool => (bool) $flag)->all());
        $this->assertNotContains(null, Widget::query()->pluck('flag')->all());
    }

    public function test_a_date_time_must_follow_the_configured_format(): void {
        $this->assertFailed([
            $this->failure(2, ['enable_time' => ['date']]),
            $this->failure(3, ['enable_time' => ['date']])
        ], $this->importer(['enable_time']), $this->input([
            ['enable_time' => '2026/01/02 03:04:05'],
            ['enable_time' => '2026-02-31 00:00:00']
        ]));
    }

    public function test_a_null_byte_in_a_date_time_is_a_date_failure(): void {
        $this->assertFailed([$this->failure(2, ['enable_time' => ['date']])], $this->importer(['enable_time']), $this->input([
            ['enable_time' => "2026-01-02\0 03:04:05"]
        ]));
    }

    public function test_a_blank_required_column_is_required_and_a_blank_optional_one_is_accepted(): void {
        $this->assertFailed([$this->failure(3, ['title' => ['required']])], $this->importer(['*title', 'enable_time']), $this->input([
            ['title' => 'Alpha', 'enable_time' => ''],
            ['title' => '   ']
        ]));
    }

    public function test_a_lookup_replaces_the_option_lookup_and_can_reject_a_value(): void {
        $service = $this->importer(['title'])->lookup('title', function (string $text, int $row): string {
            if ($text === 'bad') {
                invalid('title', 'custom-code');
            }

            return strtoupper($text) . $row;
        });

        $service->import($this->input([['title' => 'good']]));

        $this->assertSame('GOOD2', Widget::query()->sole()->title);
        $this->assertFailed([$this->failure(2, ['title' => ['custom-code']])], $service, $this->input([['title' => 'bad']]));
    }

    public function test_a_lookup_is_not_called_for_a_blank_value(): void {
        $called = false;

        $service = $this->importer(['*title'])->lookup('title', function () use (&$called): string {
            $called = true;

            return 'x';
        });

        $this->assertFailed([$this->failure(2, ['title' => ['required']])], $service, $this->input([['title' => '']]));
        $this->assertFalse($called);
    }

    public function test_a_guard_rejection_is_collected_without_stopping_the_later_rows(): void {
        $service = $this->importer(['title'])->guard(function (Model $model): void {
            if ($model->getAttribute('title') === 'bad') {
                invalid('title', 'guarded');
            }
        });

        $this->assertFailed([$this->failure(3, ['title' => ['guarded']])], $service, $this->input([['title' => 'one'], ['title' => 'bad'], ['title' => 'three']]));
        $this->assertSame(['one', 'three'], Widget::query()->orderBy('id')->pluck('title')->all());
    }

    public function test_the_failures_are_sorted_by_the_row_given_in_the_request(): void {
        $input = ['rows' => [
            ['row' => 9, 'values' => ['title' => '']],
            ['row' => 4, 'values' => ['title' => '']]
        ]];

        $this->assertFailed([$this->failure(4, ['title' => ['required']]), $this->failure(9, ['title' => ['required']])], $this->importer(['*title']), $input);
    }

    public function test_a_duplicate_within_the_file_is_caught_by_the_unique_rule(): void {
        $this->declare(['title' => Definition::text(unique: true)]);

        $this->assertFailed([$this->failure(3, ['title' => ['unique']])], $this->importer(['title']), $this->input([['title' => 'same'], ['title' => 'same']]));
    }

    public function test_a_database_error_fails_only_its_own_row(): void {
        $widget = Widget::forceCreate(['title' => 'Owner']);

        $this->assertFailed([
            $this->failure(2, ['*' => ['query-failed']]),
            $this->failure(3, ['amount' => ['integer']])
        ], $this->trinkets($widget), $this->input([
            ['label' => ''],
            ['label' => 'Alpha', 'amount' => 'abc'],
            ['label' => 'Beta', 'amount' => '5']
        ]));

        $this->assertSame(['Beta'], Trinket::query()->pluck('label')->all());
    }

    public function test_an_error_other_than_a_validation_failure_is_thrown(): void {
        $service = $this->importer(['title'])->guard(function (): void {
            error('data-not-found', 404);
        });

        $this->expectExceptionMessage('data-not-found');

        $service->import($this->input([['title' => 'Alpha']]));
    }

    public function test_a_malformed_body_is_refused(): void {
        foreach ([[], ['rows' => []], ['rows' => 'x'], ['rows' => [['row' => 2]]], ['rows' => [['row' => '2', 'values' => []]]]] as $input) {
            try {
                $this->importer(['title'])->import($input);

                $this->fail('The body was expected to be refused.');
            } catch (ServiceException $exception) {
                $this->assertSame('validation-failed', $exception->getError());
            }
        }
    }

    public function test_a_located_import_writes_the_filled_cells_and_keeps_the_blank_ones(): void {
        $first = Widget::forceCreate(['title' => 'Alpha', 'enable_time' => '2026-01-02 03:04:05']);
        $second = Widget::forceCreate(['title' => 'Beta']);

        $result = $this->located($this->importer(['title', 'enable_time']))->import($this->input([
            ['key' => strval($first->id), 'title' => 'Gamma', 'enable_time' => ''],
            ['key' => strval($second->id), 'title' => '', 'enable_time' => '2026-03-04 05:06:07']
        ]));

        $this->assertSame(['count' => 2], $result);
        $this->assertSame(2, Widget::query()->count());
        $this->assertSame(['Gamma', '2026-01-02 03:04:05'], [$first->refresh()->title, $first->enable_time?->format('Y-m-d H:i:s')]);
        $this->assertSame(['Beta', '2026-03-04 05:06:07'], [$second->refresh()->title, $second->enable_time?->format('Y-m-d H:i:s')]);
    }

    public function test_a_located_row_with_only_blank_cells_is_skipped_without_looking_it_up(): void {
        $called = false;

        $service = $this->importer(['title'])->locate(function () use (&$called): Model {
            $called = true;

            return Widget::forceCreate(['title' => 'Alpha']);
        });
        $result = $service->import($this->input([['key' => '999', 'title' => ' ']]));

        $this->assertSame(['count' => 0], $result);
        $this->assertFalse($called);
    }

    public function test_a_row_the_locator_rejects_fails_without_stopping_the_later_rows(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha']);
        $service = $this->located($this->importer(['title']));

        $this->assertFailed([$this->failure(2, ['key' => ['exists']])], $service, $this->input([
            ['key' => '999', 'title' => 'Lost'],
            ['key' => strval($widget->id), 'title' => 'Beta']
        ]));
        $this->assertSame('Beta', $widget->refresh()->title);
    }

    public function test_the_locator_query_is_narrowed_to_the_parent(): void {
        $owner = Widget::forceCreate(['title' => 'Owner']);
        $other = Widget::forceCreate(['title' => 'Other']);
        $trinket = Trinket::forceCreate(['label' => 'Alpha', 'widget_id' => $other->id]);

        $this->assertFailed([$this->failure(2, ['key' => ['exists']])], $this->located($this->trinkets($owner)), $this->input([
            ['key' => strval($trinket->id), 'label' => 'Moved']
        ]));
        $this->assertSame('Alpha', $trinket->refresh()->label);
    }

    public function test_a_located_record_keeps_its_own_unique_value(): void {
        $widget = Widget::forceCreate(['title' => 'Owner']);
        $trinket = Trinket::forceCreate(['label' => 'Alpha', 'widget_id' => $widget->id, 'amount' => 5]);

        $this->located($this->trinkets($widget))->import($this->input([['key' => strval($trinket->id), 'label' => 'Beta', 'amount' => '5']]));

        $this->assertSame(['Beta', 5], [$trinket->refresh()->label, $trinket->amount]);
    }

}
