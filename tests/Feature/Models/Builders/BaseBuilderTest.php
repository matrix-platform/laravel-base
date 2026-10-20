<?php //>

namespace Tests\Feature\Models\Builders;

use MatrixPlatform\Models\Builders\BaseBuilder;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\Widget;

class BaseBuilderTest extends FeatureTestCase {

    /**
     * @param BaseBuilder<Widget> $query
     * @return list<string>
     */
    private function titles(BaseBuilder $query): array {
        return array_values($query->pluck('title')->all());
    }

    public function test_where_active_accepts_a_started_row_without_an_end(): void {
        Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()->subDay()]);

        $this->assertSame(['alpha'], $this->titles(Widget::query()->whereActive()));
    }

    public function test_where_active_accepts_a_row_inside_its_window(): void {
        Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()->subDay(), 'disable_time' => now()->addDay()]);

        $this->assertSame(['alpha'], $this->titles(Widget::query()->whereActive()));
    }

    public function test_where_active_rejects_a_row_that_never_started(): void {
        Widget::forceCreate(['title' => 'alpha']);

        $this->assertSame([], $this->titles(Widget::query()->whereActive()));
    }

    public function test_where_active_rejects_a_row_that_starts_later(): void {
        Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()->addDay()]);

        $this->assertSame([], $this->titles(Widget::query()->whereActive()));
    }

    public function test_where_active_rejects_a_row_that_already_ended(): void {
        Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()->subDay(), 'disable_time' => now()->subHour()]);

        $this->assertSame([], $this->titles(Widget::query()->whereActive()));
    }

    public function test_where_expired_needs_a_value_in_the_past(): void {
        Widget::forceCreate(['title' => 'past', 'enable_time' => now()->subDay()]);
        Widget::forceCreate(['title' => 'future', 'enable_time' => now()->addDay()]);
        Widget::forceCreate(['title' => 'null']);

        $this->assertSame(['past'], $this->titles(Widget::query()->whereExpired('enable_time')));
    }

    public function test_where_not_expired_accepts_null_and_the_future(): void {
        Widget::forceCreate(['title' => 'past', 'disable_time' => now()->subDay()]);
        Widget::forceCreate(['title' => 'future', 'disable_time' => now()->addDay()]);
        Widget::forceCreate(['title' => 'null']);

        $this->assertSame(['future', 'null'], $this->titles(Widget::query()->whereNotExpired('disable_time')->orderBy('title')));
    }

    public function test_where_active_qualifies_the_columns_with_the_root_table(): void {
        $widget = Widget::forceCreate(['title' => 'joined', 'enable_time' => now()->subDay()]);

        Gadget::forceCreate(['title' => 'gadget', 'widget_id' => $widget->id]);

        $query = Gadget::query()
            ->join('stub_widget as widget', 'widget.id', '=', 'stub_gadget.widget_id')
            ->select('stub_gadget.*')
            ->whereActive();

        $this->assertSame([], $query->pluck('stub_gadget.title')->all());
    }

    public function test_where_active_keeps_an_already_qualified_column(): void {
        Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()->subDay()]);

        $this->assertSame(['alpha'], $this->titles(Widget::query()->whereActive('stub_widget.enable_time', 'stub_widget.disable_time')));
    }

    public function test_the_or_inside_where_not_expired_stays_grouped(): void {
        Widget::forceCreate(['title' => 'wanted']);
        Widget::forceCreate(['title' => 'other']);

        $query = Widget::query()->where('title', 'wanted')->whereNotExpired('disable_time');

        $this->assertSame(['wanted'], $this->titles($query));
    }

    public function test_a_valid_key_is_found_as_a_string_or_an_integer(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $this->assertNotNull(Widget::query()->find($widget->id));
        $this->assertNotNull(Widget::query()->find(strval($widget->id)));
    }

    public function test_a_key_that_cannot_exist_matches_nothing_without_reaching_the_database(): void {
        Widget::forceCreate(['title' => 'alpha']);

        foreach (['abc', '', '1.5', '-1', -1, '99999999999', 2147483648] as $key) {
            $this->assertNull(Widget::query()->find($key), var_export($key, true));
        }
    }

    public function test_invalid_keys_are_dropped_from_a_list(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $this->assertSame(['alpha'], $this->titles(Widget::query()->whereKey(['abc', $widget->id, '99999999999'])));
        $this->assertSame([], $this->titles(Widget::query()->whereKey(['abc', '-1'])));
    }

}
