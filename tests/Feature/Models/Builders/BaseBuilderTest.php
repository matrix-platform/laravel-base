<?php //>

namespace Tests\Feature\Models\Builders;

use MatrixPlatform\Models\Builders\BaseBuilder;
use Tests\FeatureTestCase;
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

    public function test_the_or_inside_where_not_expired_stays_grouped(): void {
        Widget::forceCreate(['title' => 'wanted']);
        Widget::forceCreate(['title' => 'other']);

        $query = Widget::query()->where('title', 'wanted')->whereNotExpired('disable_time');

        $this->assertSame(['wanted'], $this->titles($query));
    }

}
