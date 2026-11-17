<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use MatrixPlatform\Models\Block;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\TestNestedCompositeFields;

class CompositeTest extends FeatureTestCase {

    private const COLUMNS = ['page_id', 'type', '*title', 'data'];

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();
        $this->useBlockDataFixtures();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function block(string $type, array $data): Block {
        return Block::forceCreate(['page_id' => PageFactory::new()->createOne()->id, 'type' => $type, 'title' => $type, 'data' => $data]);
    }

    private function updater(): UpdateService {
        return (new UpdateService(Block::class))->standalone(true)->columns(self::COLUMNS);
    }

    public function test_an_insert_writes_a_subfield_into_its_group_without_the_storage_range(): void {
        $id = (new InsertService(Block::class))
            ->standalone(true)
            ->columns(self::COLUMNS)
            ->insert(['page_id' => PageFactory::new()->createOne()->id, 'type' => 'gallery', 'title' => 'Slides', 'data__seconds' => '99999999999'])['id'];

        $this->assertSame(['seconds' => '99999999999'], Block::query()->findOrFail(intval($id))->data);
    }

    public function test_an_update_writes_a_subfield_into_its_group_without_the_storage_range(): void {
        $block = $this->block('gallery', ['seconds' => 5]);

        $this->updater()->update($block->id, ['page_id' => $block->page_id, 'type' => 'gallery', 'title' => 'Slides', 'data__seconds' => '99999999999']);

        $this->assertSame(['seconds' => '99999999999'], $block->refresh()->data);
    }

    public function test_each_row_expands_its_own_variant(): void {
        $gallery = $this->block('gallery', ['seconds' => 5]);
        $editor = $this->block('editor', ['content' => ['tw' => '甲', 'en' => 'A']]);
        $service = $this->updater();

        $rows = $service->rows(null);
        $form = $service->form($gallery->id, null);

        $this->assertSame([$gallery->id, $editor->id], array_column($rows, 'id'));
        $this->assertSame(5, $rows[0]['data__seconds']);
        $this->assertArrayNotHasKey('data__content__tw', $rows[0]);
        $this->assertSame(['甲', 'A'], [$rows[1]['data__content__tw'], $rows[1]['data__content__en']]);
        $this->assertArrayNotHasKey('data__seconds', $rows[1]);
        $this->assertSame(['page_id', 'type', 'title', 'data__seconds'], array_column($form, 'name'));
    }

    public function test_a_readonly_composite_keeps_its_stored_data_on_update(): void {
        $block = $this->block('editor', ['content' => ['tw' => 'kept', 'en' => 'kept']]);

        (new UpdateService(Block::class))
            ->standalone(true)
            ->columns(['*title', '!data'])
            ->update($block->id, ['title' => 'b']);

        $block->refresh();

        $this->assertSame('b', $block->title);
        $this->assertEquals(['content' => ['tw' => 'kept', 'en' => 'kept']], $block->data);
    }

    public function test_a_composite_inside_a_composite_is_refused(): void {
        $this->useVariants(['block-data' => ['nested' => TestNestedCompositeFields::class]]);

        $block = $this->block('nested', []);

        $this->refuses('nested-composite-not-supported', fn () => $this->updater()->form($block->id, null));
    }

}
