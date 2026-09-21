<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Models\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Factories\BlockFactory;
use Tests\Factories\BlockItemFactory;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;

class CommonControllerTest extends FeatureTestCase {

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidParents(): array {
        return [
            'a non-integer string' => ['not-a-number'],
            'an array' => [['an-array']]
        ];
    }

    private function child(Menu $parent, string $title, int $ranking): Menu {
        $menu = $this->node($title, $ranking);

        $menu->parent_id = $parent->id;

        $menu->save();

        return $menu;
    }

    private function node(string $title, int $ranking): Menu {
        $menu = new Menu();

        $menu->title__en = $title;
        $menu->ranking = $ranking;
        $menu->enable_time = now()->subDay();

        $menu->save();

        return $menu;
    }

    private function blocks(Page $page, int $count): void {
        foreach (range(1, $count) as $ignored) {
            $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

            BlockItemFactory::new()->createOne(['block_id' => $block->id]);
        }
    }

    public function test_the_city_endpoint_answers_without_any_identity(): void {
        $taipei = new City();

        $taipei->title__en = 'Taipei';
        $taipei->ranking = 100;

        $taipei->save();

        $area = new CityArea();

        $area->city_id = $taipei->id;
        $area->title__en = 'Daan';
        $area->post_code = '106';
        $area->ranking = 100;

        $area->save();

        $response = $this->postJson('api/common/city');

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
            'data' => [
                ['id' => $taipei->id, 'title' => 'Taipei', 'areas' => [['id' => $area->id, 'title' => 'Daan', 'post_code' => '106']]]
            ]
        ]);
    }

    public function test_the_menu_endpoint_answers_without_any_identity(): void {
        $this->node('root', 100);

        $response = $this->postJson('api/common/menu');

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_the_menu_endpoint_without_a_body_returns_the_whole_tree(): void {
        $root = $this->node('root', 100);

        $this->child($root, 'child', 100);

        $response = $this->postJson('api/common/menu');

        $response->assertOk();
        $this->assertSame('root', $response->json('data.0.title'));
        $this->assertSame('child', $response->json('data.0.children.0.title'));
        $this->assertSame(2, substr_count(strval($response->getContent()), '"data":{}'));
    }

    public function test_the_menu_endpoint_with_a_parent_returns_that_subtree(): void {
        $root = $this->node('root', 100);

        $this->child($root, 'child', 100);

        $response = $this->postJson('api/common/menu', ['parent' => $root->id]);

        $response->assertOk();
        $this->assertSame('child', $response->json('data.0.title'));
        $this->assertNull($response->json('data.1'));
    }

    #[DataProvider('invalidParents')]
    public function test_an_invalid_parent_is_rejected_as_a_validation_failure(mixed $parent): void {
        $response = $this->postJson('api/common/menu', ['parent' => $parent]);

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
    }

    public function test_the_page_endpoint_answers_without_any_identity(): void {
        $page = PageFactory::new()->createOne(['path' => 'about-us', 'title' => 'About Us']);

        $response = $this->postJson('api/common/page', ['path' => 'about-us']);

        $response->assertOk();
        $response->assertJsonPath('data.id', $page->id);
        $response->assertJsonPath('data.path', 'about-us');
        $response->assertJsonPath('data.title', 'About Us');
    }

    public function test_the_page_endpoint_rejects_a_missing_path(): void {
        $this->postJson('api/common/page')
            ->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
    }

    public function test_an_unscheduled_page_is_not_found(): void {
        PageFactory::new()->createOne(['path' => 'draft', 'enable_time' => null]);

        $this->postJson('api/common/page', ['path' => 'draft'])
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_the_page_endpoint_returns_a_two_level_tree_in_ranking_order(): void {
        $page = PageFactory::new()->createOne(['path' => 'about-us']);
        $second = BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'second', 'ranking' => 200]);
        $first = BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'first', 'ranking' => 100]);

        BlockItemFactory::new()->createOne(['block_id' => $first->id, 'title' => 'slide-b', 'ranking' => 200]);
        BlockItemFactory::new()->createOne(['block_id' => $first->id, 'title' => 'slide-a', 'ranking' => 100]);

        $response = $this->postJson('api/common/page', ['path' => 'about-us']);

        $this->assertSame(['first', 'second'], array_column($response->json('data.children'), 'title'));
        $this->assertSame(['slide-a', 'slide-b'], array_column($response->json('data.children.0.children'), 'title'));
        $this->assertSame($second->id, $response->json('data.children.1.id'));
    }

    public function test_an_unscheduled_block_drops_out_without_hiding_the_page(): void {
        $page = PageFactory::new()->createOne(['path' => 'about-us']);

        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'live']);
        BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'hidden', 'enable_time' => null]);

        $response = $this->postJson('api/common/page', ['path' => 'about-us']);

        $response->assertOk();
        $this->assertSame(['live'], array_column($response->json('data.children'), 'title'));
    }

    public function test_an_unscheduled_item_drops_out_without_hiding_its_block(): void {
        $page = PageFactory::new()->createOne(['path' => 'about-us']);
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $block->id, 'title' => 'live']);
        BlockItemFactory::new()->createOne(['block_id' => $block->id, 'title' => 'hidden', 'enable_time' => null]);

        $response = $this->postJson('api/common/page', ['path' => 'about-us']);

        $this->assertSame(['live'], array_column($response->json('data.children.0.children'), 'title'));
    }

    public function test_every_node_carries_an_empty_data_object_and_a_fixed_type(): void {
        $page = PageFactory::new()->createOne(['path' => 'about-us']);
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $response = $this->postJson('api/common/page', ['path' => 'about-us']);
        $body = strval($response->getContent());

        $response->assertJsonPath('data.children.0.type', 'editor');
        $response->assertJsonPath('data.children.0.children.0.children', []);

        // json() decodes objects into arrays, so `{}` and `[]` are indistinguishable there.
        $this->assertSame(3, substr_count($body, '"data":{}'));
        $this->assertStringContainsString('"og_image":[]', $body);
    }

    public function test_the_seo_columns_follow_the_requested_locale(): void {
        PageFactory::new()->createOne([
            'path' => 'about-us',
            'title' => '關於我們',
            'seo_title__tw' => 'SEO 標題',
            'seo_title__en' => 'SEO Title',
            'seo_description__tw' => 'SEO 描述',
            'seo_description__en' => 'SEO Description',
            'og_image__en' => [['path' => '@cms/og.jpg']]
        ]);

        $english = $this->postJson('api/common/page', ['path' => 'about-us'], ['Matrix-Locale' => 'en']);
        $chinese = $this->postJson('api/common/page', ['path' => 'about-us'], ['Matrix-Locale' => 'tw']);

        $english->assertJsonPath('data.title', '關於我們');
        $english->assertJsonPath('data.seo_title', 'SEO Title');
        $english->assertJsonPath('data.seo_description', 'SEO Description');
        $english->assertJsonPath('data.og_image', [['path' => '@cms/og.jpg']]);

        $chinese->assertJsonPath('data.title', '關於我們');
        $chinese->assertJsonPath('data.seo_title', 'SEO 標題');
        $chinese->assertJsonPath('data.og_image', []);
    }

    public function test_a_translatable_subfield_follows_the_requested_locale(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne(['path' => 'about-us']);
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        BlockItemFactory::new()->createOne([
            'block_id' => $block->id,
            'data' => ['caption' => ['tw' => '中文說明', 'en' => 'English caption'], 'image' => []]
        ]);

        $english = $this->postJson('api/common/page', ['path' => 'about-us'], ['Matrix-Locale' => 'en']);
        $chinese = $this->postJson('api/common/page', ['path' => 'about-us'], ['Matrix-Locale' => 'tw']);

        $english->assertJsonPath('data.children.0.children.0.data.caption', 'English caption');
        $chinese->assertJsonPath('data.children.0.children.0.data.caption', '中文說明');
    }

    public function test_a_subfield_that_is_not_translatable_is_left_alone(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne(['path' => 'about-us']);

        BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery', 'data' => ['seconds' => 5]]);

        $this->postJson('api/common/page', ['path' => 'about-us'])->assertJsonPath('data.children.0.data.seconds', 5);
    }

    public function test_the_menu_endpoint_flattens_its_translatable_subfields_too(): void {
        $this->useMenuDataFixtures();

        $menu = new Menu();

        $menu->title__en = 'About';
        $menu->ranking = 100;
        $menu->enable_time = now()->subDay();
        $menu->data = ['caption' => ['tw' => '中文標語', 'en' => 'English lead'], 'image' => []];

        $menu->save();

        $this->postJson('api/common/menu', [], ['Matrix-Locale' => 'en'])
            ->assertJsonPath('data.0.data.caption', 'English lead');
    }

    public function test_the_page_tree_costs_the_same_however_many_blocks_it_carries(): void {
        $small = PageFactory::new()->createOne(['path' => 'small']);
        $large = PageFactory::new()->createOne(['path' => 'large']);

        $this->blocks($small, 1);
        $this->blocks($large, 8);

        // The first call of the process also loads the cfg bundle, which reads the override table once.
        $this->postJson('api/common/page', ['path' => 'small'])->assertOk();

        $lean = $this->queryCount('select', fn () => $this->postJson('api/common/page', ['path' => 'small'])->assertOk());
        $heavy = $this->queryCount('select', fn () => $this->postJson('api/common/page', ['path' => 'large'])->assertOk());

        $this->assertSame($lean, $heavy);
    }

    public function test_the_error_message_follows_the_requested_locale(): void {
        $fallback = $this->postJson('api/common/menu', ['parent' => 'not-a-number']);
        $localised = $this->postJson('api/common/menu', ['parent' => 'not-a-number'], ['Matrix-Locale' => 'tw']);

        $this->assertNotSame($fallback->json('message'), $localised->json('message'));
    }

}
