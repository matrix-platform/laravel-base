<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class QueryCountTest extends FeatureTestCase {

    private const COLUMNS = ['label', 'amount', 'widget.title'];

    private Trinket $trinket;

    private Widget $widget;

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        $this->widget = Widget::forceCreate(['title' => 'Alpha']);
        $this->trinket = Trinket::forceCreate(['label' => 'first', 'widget_id' => $this->widget->id, 'amount' => 1]);

        Trinket::forceCreate(['label' => 'second', 'widget_id' => $this->widget->id, 'amount' => 2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function params(): array {
        return ['widget_id' => $this->widget->id];
    }

    public function test_a_list_sends_a_fixed_number_of_queries(): void {
        $count = $this->queryCount('', fn () => (new ListService(Trinket::class))
            ->params($this->params())
            ->columns(self::COLUMNS)
            ->list(['filters' => ['amount' => ['op' => 'between', 'from' => 1, 'to' => 9]], 'sort' => [['name' => 'amount', 'direction' => 'desc']]]));

        $this->assertSame(6, $count);
    }

    public function test_an_export_sends_a_fixed_number_of_queries(): void {
        $count = $this->queryCount('', fn () => (new ExportService(Trinket::class))
            ->params($this->params())
            ->columns(self::COLUMNS)
            ->filterColumns(['amount'])
            ->export(['filters' => ['amount' => ['op' => 'between', 'from' => 1, 'to' => 9]], 'sort' => [['name' => 'amount', 'direction' => 'desc']]]));

        $this->assertSame(3, $count);
    }

    public function test_a_get_sends_a_fixed_number_of_queries(): void {
        $count = $this->queryCount('', fn () => (new GetService(Trinket::class))
            ->params($this->params())
            ->columns(self::COLUMNS)
            ->get($this->trinket->id));

        $this->assertSame(5, $count);
    }

    public function test_an_update_sends_a_fixed_number_of_queries(): void {
        $count = $this->queryCount('', fn () => (new UpdateService(Trinket::class))
            ->params($this->params())
            ->columns(self::COLUMNS)
            ->update($this->trinket->id, ['label' => 'renamed', 'amount' => '3']));

        $this->assertSame(4, $count);
    }

}
