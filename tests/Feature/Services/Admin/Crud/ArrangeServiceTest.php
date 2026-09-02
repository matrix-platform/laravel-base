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

    public function test_a_row_without_an_enable_time_is_disabled(): void {
        $this->widget('Alpha');

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([false], array_column($rows, 'enabled'));
    }

    public function test_a_future_enable_time_is_not_yet_enabled(): void {
        $widget = $this->widget('Alpha');
        $widget->enable_time = now()->addDay();
        $widget->save();

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([false], array_column($rows, 'enabled'));
    }

    public function test_a_past_enable_time_without_a_disable_time_is_enabled(): void {
        $widget = $this->widget('Alpha');
        $widget->enable_time = now()->subDay();
        $widget->save();

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([true], array_column($rows, 'enabled'));
    }

    public function test_a_past_disable_time_turns_an_enabled_row_off(): void {
        $widget = $this->widget('Alpha');
        $widget->enable_time = now()->subDay();
        $widget->disable_time = now()->subHour();
        $widget->save();

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([false], array_column($rows, 'enabled'));
    }

    public function test_a_future_disable_time_stays_enabled(): void {
        $widget = $this->widget('Alpha');
        $widget->enable_time = now()->subDay();
        $widget->disable_time = now()->addDay();
        $widget->save();

        $rows = (new ArrangeService(Widget::class))->standalone(true)->items()['rows'];

        $this->assertSame([true], array_column($rows, 'enabled'));
    }

    public function test_the_sortable_flag_reflects_the_rankable_setting(): void {
        $this->widget('Alpha');

        $disabled = (new ArrangeService(Widget::class))->standalone(true)->items()['sortable'];
        $enabled = (new ArrangeService(Widget::class))->standalone(true)->rankable(true)->items()['sortable'];

        $this->assertFalse($disabled);
        $this->assertTrue($enabled);
    }

    public function test_naming_a_row_enables_it_and_sets_the_enable_time(): void {
        $alpha = $this->widget('Alpha');

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $this->assertNotNull($alpha->refresh()->enable_time);
        $this->assertNull($alpha->disable_time);
    }

    public function test_leaving_a_row_out_disables_it_and_sets_the_disable_time(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->subDay();
        $alpha->save();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => []]);

        $this->assertNotNull($alpha->refresh()->disable_time);
    }

    public function test_re_enabling_a_disabled_row_clears_its_disable_time(): void {
        $alpha = $this->widget('Alpha');
        $alpha->enable_time = now()->subDay();
        $alpha->disable_time = now()->subHour();
        $alpha->save();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $this->assertNull($alpha->refresh()->disable_time);
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

    public function test_a_non_rankable_save_leaves_the_ranking_untouched(): void {
        $alpha = $this->widget('Alpha');
        $alpha->ranking = 100;
        $alpha->save();

        (new ArrangeService(Widget::class))->standalone(true)->save(['enabled' => [$alpha->id]]);

        $this->assertSame(100, $alpha->refresh()->ranking);
    }

    public function test_a_guard_can_refuse_the_whole_save(): void {
        $alpha = $this->widget('Alpha');

        $this->expectException(ServiceException::class);

        (new ArrangeService(Widget::class))
            ->standalone(true)
            ->guard(fn () => error('permission-denied', 403))
            ->save(['enabled' => [$alpha->id]]);
    }

}
