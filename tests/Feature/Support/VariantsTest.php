<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Support\Variants;
use Tests\Factories\BlockFactory;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\TestPageTypeResolver;

class VariantsTest extends FeatureTestCase {

    private function variants(): Variants {
        return app(Variants::class);
    }

    public function test_a_block_resolves_to_its_own_type_without_a_configured_driver(): void {
        $block = new Block();
        $block->type = 'banner';

        $this->assertSame('banner', $this->variants()->type('block-data', $block, null));
        $this->assertSame('gallery', $this->variants()->type('block-data', null, ['type' => 'gallery']));
    }

    public function test_a_block_item_resolves_to_the_type_of_its_block_without_a_configured_driver(): void {
        $item = new BlockItem();
        $item->block_id = BlockFactory::new()->createOne(['page_id' => PageFactory::new()->createOne()->id, 'type' => 'gallery'])->id;

        $this->assertSame('gallery', $this->variants()->type('block-item-data', $item, null));
    }

    public function test_a_configured_driver_replaces_the_built_in_resolver(): void {
        config(['matrix.variants.block-data.driver' => TestPageTypeResolver::class]);

        $block = new Block();
        $block->type = 'banner';

        $this->assertSame('page', $this->variants()->type('block-data', $block, null));
    }

    public function test_other_groups_still_have_no_resolver_without_a_driver(): void {
        $this->assertNull($this->variants()->type('page-data', null, ['type' => 'page']));
    }

    public function test_container_binding_is_scoped_to_a_single_instance(): void {
        $variants = $this->variants();

        $this->assertSame($variants, $this->variants());

        app()->forgetScopedInstances();

        $this->assertNotSame($variants, $this->variants());
    }

}
