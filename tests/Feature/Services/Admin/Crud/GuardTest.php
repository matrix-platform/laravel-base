<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ImportService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class GuardTest extends FeatureTestCase {

    /**
     * @var list<array{0: mixed, 1: mixed}>
     */
    private array $calls = [];

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', enable: 'enable_time', disable: 'disable_time')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
    }

    private function recorder(): Closure {
        return function (Model $model, mixed $context): void {
            $this->calls[] = [$model->getAttribute('title'), $context];
        };
    }

    private function refuse(): never {
        error('permission-denied', 403);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_a_list_guards_every_row_before_it_is_serialised(): void {
        $this->widget('Alpha');
        $this->widget('Beta');

        $rows = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->sorting(['title'])
            ->guard(function (Widget $widget, mixed $context): void {
                $this->calls[] = [$widget->title, $context];

                $widget->title = strtoupper(strval($widget->title));
            })
            ->list([])['rows'];

        $this->assertSame([['Alpha', null], ['Beta', null]], $this->calls);
        $this->assertSame(['ALPHA', 'BETA'], array_column($rows, 'title'));
    }

    public function test_a_get_guards_the_record_after_expanding_it_and_before_serialising_it(): void {
        $this->useBlockDataFixtures();

        $block = Block::forceCreate(['page_id' => PageFactory::new()->createOne()->id, 'type' => 'editor', 'title' => 'Alpha', 'data' => []]);

        $result = (new GetService(Block::class))
            ->standalone(true)
            ->columns(['type', 'title', 'data'])
            ->guard(function (Block $block, mixed $context): void {
                $this->calls[] = [$block->title, $context];

                $block->type = 'gallery';
                $block->title = 'Guarded';
            })
            ->get($block->id);

        $this->assertSame([['Alpha', null]], $this->calls);
        $this->assertSame(['type', 'title', 'data__content'], array_column($result['columns'], 'name'));
        $this->assertSame('Guarded', $result['data']['title']);
    }

    public function test_an_export_guards_every_row_without_a_context(): void {
        $this->widget('Alpha');
        $this->widget('Beta');

        (new ExportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard($this->recorder())
            ->export([]);

        $this->assertSame([['Alpha', null], ['Beta', null]], $this->calls);
    }

    public function test_an_insert_guards_the_assigned_and_attached_record_before_saving_it(): void {
        $widget = $this->widget('Owner');
        $seen = [];

        (new InsertService(Trinket::class))
            ->params(['widget_id' => $widget->id])
            ->columns(['label'])
            ->guard(function (Trinket $trinket, mixed $context) use (&$seen): void {
                $seen = [$trinket->exists, $trinket->label, $trinket->widget_id, $context, Trinket::query()->count()];
            })
            ->insert(['label' => 'fresh']);

        $this->assertSame([false, 'fresh', $widget->id, null, 0], $seen);
        $this->assertSame(1, Trinket::query()->count());
    }

    public function test_an_update_guards_the_assigned_record_with_its_previous_values_before_saving_it(): void {
        $widget = $this->widget('Alpha');
        $seen = [];

        (new UpdateService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard(function (Widget $widget, mixed $context) use (&$seen): void {
                $seen = [$widget->title, is_array($context) ? $context['title'] : $context, Widget::query()->sole()->title];
            })
            ->update($widget->id, ['title' => 'Beta']);

        $this->assertSame(['Beta', 'Alpha', 'Alpha'], $seen);
        $this->assertSame('Beta', $widget->refresh()->title);
    }

    public function test_an_import_guards_an_inserted_row_without_a_context(): void {
        (new ImportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard($this->recorder())
            ->import(['rows' => [['row' => 2, 'values' => ['title' => 'Alpha']]]]);

        $this->assertSame([['Alpha', null]], $this->calls);
    }

    public function test_an_import_guards_a_located_row_with_its_previous_values(): void {
        $this->useMenuFixtures('crud');

        $widget = $this->widget('Alpha');
        $seen = [];

        (new ImportService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->locate(fn (Builder $query): Model => $query->findOrFail($widget->id))
            ->guard(function (Widget $widget, mixed $context) use (&$seen): void {
                $seen = [$widget->title, is_array($context) ? $context['title'] : $context];
            })
            ->import(['rows' => [['row' => 2, 'values' => ['title' => 'Beta']]]]);

        $this->assertSame(['Beta', 'Alpha'], $seen);
    }

    public function test_the_editing_reads_do_not_run_the_guard(): void {
        $widget = $this->widget('Alpha');
        $updater = (new UpdateService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard($this->refuse(...));

        $this->assertSame('Alpha', $updater->row($updater->find($widget->id))['title']);
        $this->assertSame(['Alpha'], array_column($updater->rows(null), 'title'));
        $this->assertSame(['title'], array_column($updater->form($widget->id, null), 'name'));
        $this->assertSame(['title'], array_column((new InsertService(Widget::class))->standalone(true)->columns(['title'])->guard($this->refuse(...))->form(null), 'name'));
        $this->assertCount(1, (new ArrangeService(Widget::class))->standalone(true)->guard($this->refuse(...))->items()['rows']);
    }

    public function test_an_arrange_guards_each_record_after_applying_the_schedule_and_before_saving_it(): void {
        $alpha = $this->widget('Alpha');
        $seen = [];

        (new ArrangeService(Widget::class))
            ->standalone(true)
            ->guard(function (Widget $widget) use (&$seen): void {
                $seen = [$widget->enable_time !== null, Widget::query()->sole()->enable_time];
            })
            ->save(['enabled' => [$alpha->id]]);

        $this->assertSame([true, null], $seen);
        $this->assertNotNull($alpha->refresh()->enable_time);
    }

    public function test_a_delete_guards_and_checks_the_references_one_record_at_a_time(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        Trinket::forceCreate(['label' => 'a', 'widget_id' => $alpha->id]);

        $this->refuses('data-in-use', fn () => (new DeleteService(Widget::class))
            ->standalone(true)
            ->guard($this->recorder())
            ->delete(['id' => [$alpha->id, $beta->id]]));

        $this->assertSame([['Alpha', null]], $this->calls);

        $this->refuses('permission-denied', fn () => (new DeleteService(Widget::class))
            ->standalone(true)
            ->guard($this->refuse(...))
            ->delete(['id' => [$alpha->id, $beta->id]]));

        $this->assertSame(2, Widget::query()->count());
    }

}
