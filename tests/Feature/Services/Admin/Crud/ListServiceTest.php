<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class ListServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();
    }

    private function arrangeable(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', enable: 'enable_time', disable: 'disable_time'), [
            'title' => Definition::text()
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduled(mixed $value, string $op = 'eq'): array {
        return (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->sorting(['title'])
            ->list(['filters' => ['schedule' => ['op' => $op, 'value' => $value]]]);
    }

    private function gadgets(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget'), [
            'title' => Definition::text(),
            'widget_id' => Definition::integer()
        ]));
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    private function widgets(): void {
        Widget::forceCreate(['title' => 'Alpha', 'enable_time' => now()->subDay()]);
        Widget::forceCreate(['title' => 'Alpine', 'enable_time' => now()->subDay(), 'disable_time' => now()->addDay()]);
        Widget::forceCreate(['title' => 'Beta']);
    }

    public function test_an_arrangeable_model_reports_the_enabled_state_and_hides_the_raw_schedule_fields(): void {
        $this->arrangeable();

        $widget = $this->widget('Alpha');
        $widget->enable_time = now()->subDay();
        $widget->save();

        $this->widget('Beta');

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([]);

        $this->assertSame(['Alpha' => true, 'Beta' => false], array_column($result['rows'], 'enabled', 'title'));
        $this->assertArrayNotHasKey('enable_time', $result['rows'][0]);
        $this->assertArrayNotHasKey('disable_time', $result['rows'][0]);
        $this->assertSame(['id', 'title', 'enabled', 'actions'], array_keys($result['rows'][0]));
        $this->assertSame(['title'], array_column($result['columns'], 'name'));
        $this->assertSame(['arrange'], $result['features']);
    }

    public function test_a_non_arrangeable_model_has_no_enabled_state_or_arrange_feature(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), ['title' => Definition::text()]));

        $this->widget('Alpha');

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([]);

        $this->assertArrayNotHasKey('enabled', $result['rows'][0]);
        $this->assertSame([], $result['features']);
    }

    public function test_selects_adds_extra_fields_that_are_returned_in_rows(): void {
        $this->arrangeable();

        $widget = $this->widget('Alpha');
        $widget->ip = '10.0.0.1';
        $widget->save();

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->selects(['ip'])
            ->list([]);

        $this->assertSame('10.0.0.1', $result['rows'][0]['ip']);
        $this->assertSame(['title'], array_column($result['columns'], 'name'));
    }

    public function test_selects_does_not_duplicate_a_field_already_in_columns(): void {
        $this->arrangeable();

        $this->widget('Alpha');

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->selects(['title'])
            ->list([]);

        $this->assertSame('Alpha', $result['rows'][0]['title']);
    }

    public function test_an_arrangeable_model_offers_the_schedule_filter(): void {
        $this->arrangeable();

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([]);

        $this->assertCount(1, $result['filters']);

        $filter = $result['filters'][0];

        $this->assertSame('schedule', $filter['name']);
        $this->assertSame('Schedule', $filter['title']);
        $this->assertSame('eq', $filter['op']);
        $this->assertSame('select', $filter['presentation']);
        $this->assertFalse($filter['sortable']);
        $this->assertFalse($filter['writable']);
        $this->assertSame(['enabled', 'disabling', 'disabled', 'enabling', 'expired'], array_column($filter['options'], 'id'));
        $this->assertSame(['Enabled', 'Ending Soon', 'Not Enabled', 'Upcoming', 'Ended'], array_column($filter['options'], 'title'));
        $this->assertSame(['title'], array_column($result['columns'], 'name'));
    }

    public function test_a_non_arrangeable_model_offers_no_filter(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), ['title' => Definition::text()]));

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([]);

        $this->assertSame([], $result['filters']);
    }

    public function test_the_schedule_filter_narrows_the_rows_before_counting(): void {
        $this->arrangeable();
        $this->widgets();

        $enabled = $this->scheduled('enabled');
        $disabled = $this->scheduled('disabled');

        $this->assertSame(['Alpha', 'Alpine'], array_column($enabled['rows'], 'title'));
        $this->assertSame([true, true], array_column($enabled['rows'], 'enabled'));
        $this->assertSame(2, $enabled['pagination']['total']);
        $this->assertSame(['Beta'], array_column($disabled['rows'], 'title'));
        $this->assertSame([false], array_column($disabled['rows'], 'enabled'));
        $this->assertSame(1, $disabled['pagination']['total']);
        $this->assertSame(['Alpine'], array_column($this->scheduled('disabling')['rows'], 'title'));
    }

    public function test_the_schedule_filter_intersects_the_other_filters(): void {
        $this->arrangeable();
        $this->widgets();

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns([['name' => 'title', 'op' => 'contains']])
            ->list(['filters' => ['schedule' => ['op' => 'eq', 'value' => 'disabling'], 'title' => ['op' => 'contains', 'value' => 'Al']]]);

        $this->assertSame(['Alpine'], array_column($result['rows'], 'title'));
    }

    public function test_an_empty_or_mismatched_schedule_filter_is_ignored(): void {
        $this->arrangeable();
        $this->widgets();

        $this->assertSame(3, $this->scheduled(null)['pagination']['total']);
        $this->assertSame(3, $this->scheduled('')['pagination']['total']);
        $this->assertSame(3, $this->scheduled('enabled', 'neq')['pagination']['total']);
    }

    public function test_an_unknown_schedule_value_is_refused(): void {
        $this->arrangeable();

        $this->refuses('invalid-filter-value', fn () => $this->scheduled('everything'));
        $this->refuses('invalid-filter-value', fn () => $this->scheduled(['enabled']));
    }

    public function test_the_schedule_filter_is_ignored_on_a_non_arrangeable_model(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), ['title' => Definition::text()]));

        $this->widgets();

        $this->assertSame(3, $this->scheduled('enabled')['pagination']['total']);
    }

    public function test_the_schedule_filter_survives_a_joined_column(): void {
        $this->arrangeable();

        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget', enable: 'enable_time', disable: 'disable_time'), [
            'title' => Definition::text(),
            'widget_id' => Definition::integer()
        ]));

        $widget = Widget::forceCreate(['title' => 'Alpha', 'enable_time' => now()->subDay()]);

        Gadget::forceCreate(['title' => 'on', 'widget_id' => $widget->id, 'enable_time' => now()->subDay()]);
        Gadget::forceCreate(['title' => 'off', 'widget_id' => $widget->id]);

        $result = (new ListService(Gadget::class))
            ->standalone(true)
            ->columns(['title', 'widget.title'])
            ->list(['filters' => ['schedule' => ['op' => 'eq', 'value' => 'enabled']]]);

        $this->assertSame(['on'], array_column($result['rows'], 'title'));
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     * @param list<string|array<string, mixed>> $optionals
     * @return array<string, mixed>
     */
    private function pooled(array $columns, array $optionals, mixed $input = []): array {
        return (new ListService(Widget::class))
            ->standalone(true)
            ->columns($columns)
            ->optionals($optionals)
            ->sorting(['title'])
            ->list($input);
    }

    public function test_the_optional_columns_follow_their_own_order_with_the_list_only_columns_appended(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        $result = $this->pooled(['title', 'count(trinkets)'], ['ip', 'title', 'enable_time']);

        $this->assertSame(['ip', 'title', 'enable_time', 'trinkets_count'], array_column($result['columns'], 'name'));
        $this->assertSame([false, true, false, true], array_column($result['columns'], 'default'));
    }

    public function test_a_column_on_both_sides_keeps_the_list_definition(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));

        $result = $this->pooled([['name' => 'title', 'title' => 'Heading']], ['*title']);

        $this->assertSame(['Heading'], array_column($result['columns'], 'title'));
        $this->assertSame([false], array_column($result['columns'], 'required'));
    }

    public function test_the_optional_columns_leave_out_what_a_list_cannot_render(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), [
            'gallery' => Definition::composite('gallery')
        ]));

        $result = $this->pooled(['title'], ['secret', '+ghost', 'payload', 'gallery', 'ip:password', 'trinket_id:hidden', 'translated:custom-editor', 'ranking']);

        $this->assertSame(['ranking', 'title'], array_column($result['columns'], 'name'));
    }

    public function test_an_optional_column_on_a_tab_is_left_out_but_a_list_column_on_a_tab_stays(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), [
            'enable_time' => Definition::dateTime(tab: 'other')
        ]));

        $result = $this->pooled(['title', 'enable_time'], [['name' => 'ip', 'tab' => 'seo'], 'disable_time', 'enable_time', 'title']);

        $this->assertSame(['disable_time', 'enable_time', 'title'], array_column($result['columns'], 'name'));
    }

    public function test_a_json_column_stays_optional_once_its_presentation_is_declared(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));

        $result = $this->pooled(['title'], ['payload:plain', 'title']);

        $this->assertSame(['payload', 'title'], array_column($result['columns'], 'name'));
    }

    public function test_a_joined_list_column_takes_the_place_of_its_foreign_key(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget'), [
            'title' => Definition::text(),
            'widget_id' => Definition::integer()
        ]));

        $result = (new ListService(Gadget::class))
            ->standalone(true)
            ->columns(['title', 'widget.title'])
            ->optionals(['widget_id', 'title'])
            ->list([]);

        $this->assertSame(['widget_title', 'title'], array_column($result['columns'], 'name'));
        $this->assertSame(['widget_id', null], array_column($result['columns'], 'replaces'));
    }

    public function test_the_first_joined_list_column_of_a_relation_takes_the_place_of_its_foreign_key(): void {
        $this->gadgets();

        $result = (new ListService(Gadget::class))
            ->standalone(true)
            ->columns(['widget.title', 'widget.ip', 'title'])
            ->optionals(['widget_id', 'title'])
            ->list([]);

        $this->assertSame(['widget_title', 'title', 'widget_ip'], array_column($result['columns'], 'name'));
        $this->assertSame(['widget_id', null, null], array_column($result['columns'], 'replaces'));
    }

    public function test_a_foreign_key_among_the_list_columns_is_not_replaced(): void {
        $this->gadgets();

        $result = (new ListService(Gadget::class))
            ->standalone(true)
            ->columns(['title', 'widget_id', 'widget.title'])
            ->optionals(['widget_id', 'title'])
            ->list([]);

        $this->assertSame(['widget_id', 'title', 'widget_title'], array_column($result['columns'], 'name'));
        $this->assertSame([null, null, null], array_column($result['columns'], 'replaces'));
    }

    public function test_an_arrangeable_list_marks_the_defaults_after_dropping_the_schedule_columns(): void {
        $this->arrangeable();

        $result = $this->pooled(['title'], ['title', 'enable_time', 'disable_time', 'ip']);

        $this->assertSame(['title', 'ip'], array_column($result['columns'], 'name'));
        $this->assertSame([true, false], array_column($result['columns'], 'default'));
    }

    public function test_an_optional_column_is_carried_in_the_rows_and_can_be_sorted_by(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), ['trinket_id' => Definition::integer()]));

        Widget::forceCreate(['title' => 'Alpha', 'trinket_id' => 5]);
        Widget::forceCreate(['title' => 'Beta', 'trinket_id' => 17]);

        $result = $this->pooled(['title'], ['trinket_id'], ['sort' => [['name' => 'trinket_id', 'direction' => 'desc']]]);

        $this->assertSame(['Beta' => 17, 'Alpha' => 5], array_column($result['rows'], 'trinket_id', 'title'));
    }

    public function test_an_optional_column_can_be_filtered_but_a_hidden_one_cannot(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), ['trinket_id' => Definition::integer()]));

        Widget::forceCreate(['title' => 'Alpha', 'trinket_id' => 5, 'secret' => 'open']);
        Widget::forceCreate(['title' => 'Beta', 'trinket_id' => 17, 'secret' => 'sesame']);

        $byTrinket = $this->pooled(['title'], ['trinket_id'], ['filters' => ['trinket_id' => ['op' => 'between', 'from' => 10, 'to' => 20]]]);
        $bySecret = $this->pooled(['title'], ['secret'], ['filters' => ['secret' => ['op' => 'contains', 'value' => 'sesame']]]);

        $this->assertSame(['Beta'], array_column($byTrinket['rows'], 'title'));
        $this->assertSame(['Alpha', 'Beta'], array_column($bySecret['rows'], 'title'));
    }

    public function test_without_optionals_every_column_is_a_default(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title', 'count(trinkets)'])
            ->list([]);

        $this->assertSame(['title', 'trinkets_count'], array_column($result['columns'], 'name'));
        $this->assertSame([true, true], array_column($result['columns'], 'default'));
    }

}
