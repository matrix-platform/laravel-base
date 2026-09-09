<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
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

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
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

}
