<?php //>

namespace Tests\Feature\Columns\Declarations;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use MatrixPlatform\Columns\Declarations\BlockItemTypeResolver;
use MatrixPlatform\Columns\Declarations\BlockTypeResolver;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use Tests\Factories\BlockFactory;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;

class TypeResolverTest extends FeatureTestCase {

    private function block(string $type): Block {
        return BlockFactory::new()->createOne(['page_id' => PageFactory::new()->createOne()->id, 'type' => $type]);
    }

    private function routeTo(string $uri): void {
        $request = Request::create($uri);
        $route = (new Route('GET', 'admin/block/{block_id}/item', []))->bind($request);

        $request->setRouteResolver(fn (): Route => $route);

        app()->instance('request', $request);
    }

    public function test_a_stored_block_type_wins_over_the_input(): void {
        $block = (new Block())->forceFill(['type' => 'gallery']);

        $this->assertSame('gallery', (new BlockTypeResolver())->resolve($block, ['type' => 'editor']));
    }

    public function test_a_new_block_takes_its_type_from_the_input(): void {
        $this->assertSame('editor', (new BlockTypeResolver())->resolve(new Block(), ['type' => 'editor']));
        $this->assertSame('editor', (new BlockTypeResolver())->resolve(null, ['type' => 'editor']));
    }

    public function test_a_block_type_that_is_not_a_string_resolves_to_nothing(): void {
        $this->assertNull((new BlockTypeResolver())->resolve(null, ['type' => ['editor']]));
        $this->assertNull((new BlockTypeResolver())->resolve(null, 'editor'));
    }

    public function test_an_item_takes_the_type_of_the_block_it_belongs_to(): void {
        $block = $this->block('gallery');
        $item = (new BlockItem())->forceFill(['block_id' => $block->id]);

        $this->assertSame('gallery', (new BlockItemTypeResolver())->resolve($item, []));
    }

    public function test_a_new_item_takes_the_block_from_the_route(): void {
        $block = $this->block('gallery');

        $this->routeTo("admin/block/{$block->id}/item");

        $this->assertSame('gallery', (new BlockItemTypeResolver())->resolve(null, []));
    }

    public function test_an_item_without_a_block_resolves_to_nothing(): void {
        $this->assertNull((new BlockItemTypeResolver())->resolve(null, []));

        $this->routeTo('admin/block/0/item');

        $this->assertNull((new BlockItemTypeResolver())->resolve(new BlockItem(), []));
    }

}
