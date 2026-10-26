<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ImportService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\NewService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;

class ResponseShapeTest extends FeatureTestCase {

    private const FORM = ['title', 'subtitle', 'breadcrumbs', 'data', 'columns', 'preference', 'orderable', 'staged', 'actions'];

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
    }

    public function test_the_list_response_keeps_its_key_order(): void {
        Widget::forceCreate(['title' => 'Alpha']);

        $result = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([]);

        $this->assertSame(['title', 'subtitle', 'breadcrumbs', 'context', 'rows', 'columns', 'filters', 'features', 'preference', 'sorting', 'pagination', 'actions'], array_keys($result));
        $this->assertSame(['page', 'row'], array_keys($result['actions']));
        $this->assertSame(['page', 'size', 'total'], array_keys($result['pagination']));
    }

    public function test_the_get_response_keeps_its_key_order(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha']);

        $result = (new GetService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->get($widget->id);

        $this->assertSame(self::FORM, array_keys($result));
    }

    public function test_the_new_response_keeps_its_key_order(): void {
        $result = (new NewService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->new();

        $this->assertSame(self::FORM, array_keys($result));
    }

    public function test_the_export_keeps_its_key_order_and_the_order_of_its_columns(): void {
        Widget::forceCreate(['title' => 'Alpha', 'ip' => '10.0.0.1']);

        $result = (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['ip', 'title', 'enable_time'])
            ->export([]);

        $this->assertSame(['title', 'columns', 'rows'], array_keys($result));
        $this->assertSame(['ip', 'title', 'enable_time'], array_column($result['columns'], 'name'));
        $this->assertSame(['name', 'title', 'translatable', 'type', 'format', 'presentation'], array_keys($result['columns'][0]));
        $this->assertSame(['ip', 'title', 'enable_time'], array_keys($result['rows'][0]));
    }

    public function test_the_import_template_keeps_its_key_order_and_the_order_of_its_columns(): void {
        $result = (new ImportService(Widget::class))
            ->standalone(true)
            ->columns(['ip', '*title', 'enable_time'])
            ->template();

        $this->assertSame(['title', 'columns', 'rows'], array_keys($result));
        $this->assertSame(['ip', 'title', 'enable_time'], array_column($result['columns'], 'name'));
        $this->assertSame(['name', 'title', 'translatable', 'type', 'format', 'presentation', 'required', 'options'], array_keys($result['columns'][0]));
    }

}
