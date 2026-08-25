<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Services\Admin\Crud\SortService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class SortServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
    }

    /**
     * @return list<string>
     */
    private function order(): array {
        $widgets = Widget::query()
            ->orderBy('ranking')
            ->orderBy('id')
            ->get();

        return array_values($widgets->map(fn (Widget $widget): string => strval($widget->title))->all());
    }

    private function widget(string $title, int $ranking): Widget {
        return Widget::forceCreate(['title' => $title, 'ranking' => $ranking]);
    }

    public function test_the_listing_is_ordered_by_ranking_then_by_key(): void {
        $second = $this->widget('B', 100);
        $first = $this->widget('A', 100);
        $last = $this->widget('C', 200);

        $rows = (new SortService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([$second->id, $first->id, $last->id], array_column($rows, 'id'));
        $this->assertSame(['B', 'A', 'C'], array_column($rows, 'title'));
        $this->assertSame([100, 100, 200], array_column($rows, 'ranking'));
    }

    public function test_the_requested_order_is_persisted(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);
        $gamma = $this->widget('C', 300);

        (new SortService(Widget::class))
            ->standalone(true)
            ->sort(['order' => [$gamma->id, $alpha->id, $beta->id]]);

        $this->assertSame(['C', 'A', 'B'], $this->order());
    }

    public function test_only_the_rows_that_moved_are_recorded_in_the_audit_trail(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);
        $gamma = $this->widget('C', 300);

        (new SortService(Widget::class))
            ->standalone(true)
            ->sort(['order' => [$gamma->id, $alpha->id, $beta->id]]);

        $updates = ManipulationLog::query()
            ->where('data_type', 'stub_widget')
            ->where('type', ManipulationType::Updated);

        $this->assertSame(1, $updates->count());

        $log = $updates->sole();

        $this->assertSame($gamma->id, $log->data_id);
        $this->assertSame(['ranking' => 99], $log->after);
        $this->assertSame([100, 200, 99], [$alpha->refresh()->ranking, $beta->refresh()->ranking, $gamma->refresh()->ranking]);
    }

    public function test_a_missing_identifier_is_rejected(): void {
        $alpha = $this->widget('A', 100);

        $this->widget('B', 200);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-sort-order');

        (new SortService(Widget::class))->standalone(true)->sort(['order' => [$alpha->id]]);
    }

    public function test_an_unknown_identifier_is_rejected(): void {
        $alpha = $this->widget('A', 100);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-sort-order');

        (new SortService(Widget::class))->standalone(true)->sort(['order' => [$alpha->id, 999999]]);
    }

    public function test_a_duplicated_identifier_is_rejected(): void {
        $alpha = $this->widget('A', 100);

        $this->widget('B', 200);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-sort-order');

        (new SortService(Widget::class))->standalone(true)->sort(['order' => [$alpha->id, $alpha->id]]);
    }

    public function test_an_associative_order_is_normalised_instead_of_exhausting_memory(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);

        (new SortService(Widget::class))
            ->standalone(true)
            ->sort(['order' => ['second' => $beta->id, 'first' => $alpha->id]]);

        $this->assertSame(['B', 'A'], $this->order());
    }

    public function test_identifiers_sent_as_strings_are_accepted(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);

        (new SortService(Widget::class))
            ->standalone(true)
            ->sort(['order' => [strval($beta->id), strval($alpha->id)]]);

        $this->assertSame(['B', 'A'], $this->order());
    }

    public function test_a_scoped_out_row_makes_the_complete_list_invalid(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-sort-order');

        (new SortService(Widget::class))
            ->standalone(true)
            ->scope(fn (Builder $query) => $query->where('title', 'A'))
            ->sort(['order' => [$beta->id, $alpha->id]]);
    }

    public function test_a_guard_sees_the_new_ranking_not_the_old_one(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);
        $seen = [];

        (new SortService(Widget::class))
            ->standalone(true)
            ->guard(function (Widget $widget) use (&$seen): void {
                $seen[strval($widget->title)] = [$widget->getAttribute('ranking'), $widget->getOriginal('ranking')];
            })
            ->sort(['order' => [$beta->id, $alpha->id]]);

        $this->assertSame(['B' => [99, 200], 'A' => [100, 100]], $seen);
    }

    public function test_a_guard_runs_for_the_rows_that_did_not_move(): void {
        $alpha = $this->widget('A', 100);
        $beta = $this->widget('B', 200);
        $gamma = $this->widget('C', 300);
        $guarded = [];

        (new SortService(Widget::class))
            ->standalone(true)
            ->guard(function (Widget $widget) use (&$guarded): void {
                $guarded[] = strval($widget->title);
            })
            ->sort(['order' => [$gamma->id, $alpha->id, $beta->id]]);

        $this->assertSame(['C', 'A', 'B'], $guarded);
    }

    public function test_the_listing_does_not_run_the_guard(): void {
        $this->widget('A', 100);

        $rows = (new SortService(Widget::class))
            ->standalone(true)
            ->guard(fn () => error('permission-denied', 403))
            ->items()['rows'];

        $this->assertCount(1, $rows);
    }

    public function test_a_nested_listing_only_sees_its_own_parent(): void {
        $mine = $this->widget('A', 100);
        $other = $this->widget('B', 200);

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $mine->id, 'ranking' => 100]);
        Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $other->id, 'ranking' => 200]);

        $rows = (new SortService(Trinket::class))->params(['widget_id' => $mine->id])->items()['rows'];

        $this->assertSame(['mine'], array_column($rows, 'title'));
    }

    public function test_a_nested_sort_cannot_reach_another_parent(): void {
        $mine = $this->widget('A', 100);
        $other = $this->widget('B', 200);

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $mine->id, 'ranking' => 100]);

        $theirs = Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $other->id, 'ranking' => 200]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-sort-order');

        (new SortService(Trinket::class))->params(['widget_id' => $mine->id])->sort(['order' => [$theirs->id]]);
    }

    public function test_a_missing_route_parameter_is_rejected(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('data-not-found');

        (new SortService(Trinket::class))->items();
    }

}
