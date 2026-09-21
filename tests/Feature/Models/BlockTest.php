<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use Tests\Factories\BlockFactory;
use Tests\Factories\BlockItemFactory;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;

class BlockTest extends FeatureTestCase {

    public function test_the_block_table_carries_the_declared_columns(): void {
        $this->assertEqualsCanonicalizing(
            ['id', 'page_id', 'type', 'title', 'data', 'enable_time', 'disable_time', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'],
            Schema::getColumnListing('base_block')
        );
    }

    public function test_the_block_item_table_carries_the_declared_columns(): void {
        $this->assertEqualsCanonicalizing(
            ['id', 'block_id', 'title', 'data', 'enable_time', 'disable_time', 'ranking', 'creator_id', 'create_time', 'updater_id', 'update_time'],
            Schema::getColumnListing('base_block_item')
        );
    }

    public function test_both_data_columns_are_stored_as_jsonb(): void {
        $this->assertSame('jsonb', Schema::getColumnType('base_block', 'data'));
        $this->assertSame('jsonb', Schema::getColumnType('base_block_item', 'data'));
    }

    public function test_the_relations_walk_both_ways(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $this->assertSame($page->id, $block->page()->firstOrFail()->id);
        $this->assertSame([$block->id], $page->blocks->pluck('id')->all());
        $this->assertSame($block->id, $item->block()->firstOrFail()->id);
        $this->assertSame([$item->id], $block->items->pluck('id')->all());
    }

    public function test_the_data_column_reads_back_as_an_array(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        $block->data = ['fluid' => true];

        $block->save();

        $this->assertTrue(array_get_value(Block::query()->findOrFail($block->id)->data, 'fluid'));
    }

    public function test_the_schedule_columns_read_back_as_dates(): void {
        $page = PageFactory::new()->createOne();

        BlockFactory::new()->createOne([
            'page_id' => $page->id,
            'enable_time' => Carbon::parse('2026-01-02 03:04:05'),
            'disable_time' => Carbon::parse('2026-02-03 04:05:06')
        ]);

        $block = Block::query()->firstOrFail();

        $this->assertInstanceOf(Carbon::class, $block->enable_time);
        $this->assertSame('2026-01-02 03:04:05', $block->enable_time->format('Y-m-d H:i:s'));
    }

    public function test_where_active_keeps_only_blocks_inside_their_schedule(): void {
        $page = PageFactory::new()->createOne();

        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'live', 'enable_time' => now()->subDay()]);
        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'never', 'enable_time' => null]);
        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'later', 'enable_time' => now()->addDay()]);
        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'expired', 'enable_time' => now()->subDays(2), 'disable_time' => now()->subDay()]);

        $this->assertSame(['live'], Block::query()->whereActive()->pluck('title')->all());
    }

    public function test_the_foreign_keys_point_at_their_owners(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $this->assertSame(1, BlockItem::query()->where('block_id', $block->id)->count());
    }

}
