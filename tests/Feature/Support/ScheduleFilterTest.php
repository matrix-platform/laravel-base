<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Support\Schedule;
use MatrixPlatform\Support\ScheduleFilter;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\Widget;

class ScheduleFilterTest extends FeatureTestCase {

    private const ALL = ['both', 'ended', 'ending', 'ending-now', 'never', 'open', 'starting-now', 'upcoming'];

    protected function setUp(): void {
        parent::setUp();

        $this->freezeSecond();

        $rows = [
            'never' => [null, null],
            'open' => [now()->subDay(), null],
            'ending' => [now()->subDay(), now()->addDay()],
            'upcoming' => [now()->addDay(), null],
            'ended' => [now()->subDays(2), now()->subDay()],
            'both' => [now()->addDay(), now()->subDay()],
            'starting-now' => [now(), null],
            'ending-now' => [now()->subDay(), now()]
        ];

        foreach ($rows as $title => [$enable, $disable]) {
            Widget::forceCreate(['title' => $title, 'enable_time' => $enable, 'disable_time' => $disable]);
        }
    }

    /**
     * @param BaseBuilder<Widget> $query
     * @return list<string>
     */
    private function titles(BaseBuilder $query): array {
        return array_values($query->orderBy('title')->pluck('title')->all());
    }

    /**
     * @return list<string>
     */
    private function filtered(ScheduleFilter $filter): array {
        $query = Widget::query();

        $filter->apply($query, 'enable_time', 'disable_time', now());

        return $this->titles($query);
    }

    public function test_enabled_matches_rows_inside_their_window(): void {
        $this->assertSame(['ending', 'open', 'starting-now'], $this->filtered(ScheduleFilter::Enabled));
    }

    public function test_disabling_matches_enabled_rows_with_an_end_ahead(): void {
        $this->assertSame(['ending'], $this->filtered(ScheduleFilter::Disabling));
    }

    public function test_disabled_matches_every_row_that_is_not_enabled(): void {
        $this->assertSame(['both', 'ended', 'ending-now', 'never', 'upcoming'], $this->filtered(ScheduleFilter::Disabled));
    }

    public function test_enabling_matches_rows_starting_later(): void {
        $this->assertSame(['both', 'upcoming'], $this->filtered(ScheduleFilter::Enabling));
    }

    public function test_expired_matches_rows_whose_end_has_passed(): void {
        $this->assertSame(['both', 'ended', 'ending-now'], $this->filtered(ScheduleFilter::Expired));
    }

    public function test_enabled_agrees_with_where_active_and_is_enabled(): void {
        $active = array_values(Widget::query()->whereActive()->orderBy('title')->pluck('title')->all());
        $scheduled = array_values(Widget::query()->orderBy('title')->get()->filter(fn (Widget $row): bool => Schedule::isEnabled($row, 'enable_time', 'disable_time'))->pluck('title')->all());

        $this->assertSame($active, $this->filtered(ScheduleFilter::Enabled));
        $this->assertSame($scheduled, $this->filtered(ScheduleFilter::Enabled));
    }

    public function test_disabled_is_the_exact_complement_of_enabled(): void {
        $enabled = $this->filtered(ScheduleFilter::Enabled);
        $disabled = $this->filtered(ScheduleFilter::Disabled);

        $this->assertSame([], array_values(array_intersect($enabled, $disabled)));
        $this->assertEqualsCanonicalizing(self::ALL, [...$enabled, ...$disabled]);
    }

    public function test_the_or_inside_disabled_stays_grouped(): void {
        $query = Widget::query()->where('title', 'never');

        ScheduleFilter::Disabled->apply($query, 'enable_time', 'disable_time', now());

        $this->assertSame(['never'], $this->titles($query));
    }

    public function test_the_columns_are_qualified_with_the_root_table(): void {
        $widget = Widget::forceCreate(['title' => 'joined', 'enable_time' => now()->subDay()]);

        Gadget::forceCreate(['title' => 'gadget', 'widget_id' => $widget->id]);

        $query = Gadget::query()->join('stub_widget as widget', 'widget.id', '=', 'stub_gadget.widget_id')->select('stub_gadget.*');

        ScheduleFilter::Enabled->apply($query, 'enable_time', 'disable_time', now());

        $this->assertSame([], $query->pluck('stub_gadget.title')->all());
    }

}
