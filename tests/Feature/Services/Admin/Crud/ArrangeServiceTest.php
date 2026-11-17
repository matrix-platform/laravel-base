<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;

class ArrangeServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', enable: 'enable_time', disable: 'disable_time')));
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_the_enabled_flag_follows_the_schedule(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->subDay();
        $alpha->save();

        $this->widget('Beta');

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([true, false], array_column($rows, 'enabled'));
    }

    public function test_the_sortable_flag_reflects_the_rankable_setting(): void {
        $this->widget('Alpha');

        $disabled = (new ArrangeService(Widget::class))->standalone(true)->items()['sortable'];
        $enabled = (new ArrangeService(Widget::class))->standalone(true)->rankable(true)->items()['sortable'];

        $this->assertFalse($disabled);
        $this->assertTrue($enabled);
    }

    public function test_a_save_enables_the_named_rows_and_disables_the_rest(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->subDay();
        $alpha->save();

        $beta = $this->widget('Beta');

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$beta->id]]);

        $this->assertNotNull($alpha->refresh()->disable_time);
        $this->assertNotNull($beta->refresh()->enable_time);
        $this->assertNull($beta->disable_time);
    }

    public function test_re_enabling_a_disabled_row_clears_its_disable_time(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->subDay();
        $alpha->disable_time = now()->subHour();
        $alpha->save();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $this->assertNull($alpha->refresh()->disable_time);
    }

    public function test_enabling_a_row_keeps_a_scheduled_disable_time(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->addDay();
        $alpha->disable_time = now()->addWeek();
        $alpha->save();

        $disableAt = $alpha->disable_time->toDateTimeString();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $alpha->refresh();

        $this->assertNotNull($alpha->enable_time);
        $this->assertFalse($alpha->enable_time->isFuture());
        $this->assertSame($disableAt, $alpha->disable_time?->toDateTimeString());
    }

    public function test_an_unknown_identifier_is_rejected(): void {
        $this->widget('Alpha');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-arrange-order');

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [999999]]);
    }

    public function test_a_duplicated_identifier_is_rejected(): void {
        $alpha = $this->widget('Alpha');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-arrange-order');

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id, $alpha->id]]);
    }

    public function test_rankable_save_reassigns_the_ranking_by_the_given_order(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        (new ArrangeService(Widget::class))
            ->standalone(true)
            ->rankable(true)
            ->save(['enabled' => [$beta->id, $alpha->id]]);

        $this->assertLessThan($alpha->refresh()->ranking, $beta->refresh()->ranking);
    }

    public function test_a_descending_rankable_listing_and_save_follow_the_highest_ranking_first(): void {
        $alpha = $this->widget('Alpha');
        $alpha->ranking = 100;
        $alpha->save();

        $beta = $this->widget('Beta');
        $beta->ranking = 200;
        $beta->save();

        $service = (new ArrangeService(Widget::class))
            ->standalone(true)
            ->rankable(true)
            ->descending(true);

        $this->assertSame(['Beta', 'Alpha'], array_column($service->items()['rows'], 'title'));

        $service->save(['enabled' => [$alpha->id, $beta->id]]);

        $this->assertGreaterThan($beta->refresh()->ranking, $alpha->refresh()->ranking);
    }

    public function test_a_non_rankable_save_leaves_the_ranking_untouched(): void {
        $alpha = $this->widget('Alpha');
        $alpha->ranking = 100;
        $alpha->save();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $this->assertSame(100, $alpha->refresh()->ranking);
    }

}
