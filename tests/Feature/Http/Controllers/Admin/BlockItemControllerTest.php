<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Models\User;
use Tests\Factories\BlockFactory;
use Tests\Factories\BlockItemFactory;
use Tests\Factories\PageFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class BlockItemControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_every_mounted_action_answers(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id]);
        $prefix = "admin/page/{$page->id}/block/{$block->id}/item";

        foreach ([$prefix, "{$prefix}/new", "{$prefix}/{$item->id}", "{$prefix}/arrange"] as $uri) {
            $this->send($uri)->assertJsonPath('success', true);
        }

        $this->send("{$prefix}/insert", ['title' => 'a', 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
        $this->send("{$prefix}/{$item->id}/update", ['title' => 'b', 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
        $this->send("{$prefix}/arrange/save", ['enabled' => []])->assertJsonPath('success', true);
        $this->send("{$prefix}/delete", ['id' => [$item->id]])->assertJsonPath('success', true);
    }

    public function test_the_new_form_expands_the_parent_modules_item_subfields_without_any_body(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        $names = array_column($this->send("admin/page/{$page->id}/block/{$block->id}/item/new")->json('data.columns'), 'name');

        $this->assertContains('data__caption', $names);
        $this->assertContains('data__image', $names);
        $this->assertNotContains('data', $names);
    }

    public function test_the_item_subfields_are_not_the_parent_blocks_subfields(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        $names = array_column($this->send("admin/page/{$page->id}/block/{$block->id}/item/new")->json('data.columns'), 'name');

        $this->assertNotContains('data__seconds', $names);
    }

    public function test_the_item_module_bundle_overrides_only_the_keys_it_declares(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);
        $columns = $this->send("admin/page/{$page->id}/block/{$block->id}/item/new")->json('data.columns');

        $this->assertSame('Slide caption', $this->columnByName($columns, 'data__caption')['title']);
        $this->assertSame('Image', $this->columnByName($columns, 'data__image')['title']);
    }

    public function test_a_parent_without_an_item_variant_expands_nothing(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'editor']);

        $names = array_column($this->send("admin/page/{$page->id}/block/{$block->id}/item/new")->json('data.columns'), 'name');

        $this->assertSame(['title', 'enable_time', 'disable_time'], $names);
    }

    // `insert` validates before attach() puts the foreign key on the blank model.
    public function test_inserting_resolves_the_variant_from_the_route(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        $response = $this->send("admin/page/{$page->id}/block/{$block->id}/item/insert", [
            'title' => 'First',
            'data__caption__tw' => 'TW caption',
            'data__caption__en' => 'EN caption',
            'data__image' => [],
            'enable_time' => null,
            'disable_time' => null
        ]);

        $response->assertJsonPath('success', true);

        $item = BlockItem::query()->findOrFail(intval($response->json('data.id')));

        $this->assertSame('TW caption', array_get_value(array_get_value($item->data, 'caption'), 'tw'));
    }

    public function test_editing_an_item_resolves_the_variant_from_its_own_block_id(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id, 'data' => ['caption' => ['tw' => 'stored', 'en' => 'stored']]]);

        $data = $this->send("admin/page/{$page->id}/block/{$block->id}/item/{$item->id}")->json('data.data');

        $this->assertSame('stored', array_get_value($data, 'data__caption__tw'));
        $this->assertArrayNotHasKey('data', $data);
    }

    public function test_a_nested_list_is_scoped_to_its_block(): void {
        $page = PageFactory::new()->createOne();
        $mine = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $theirs = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $mine->id, 'title' => 'mine']);
        BlockItemFactory::new()->createOne(['block_id' => $theirs->id, 'title' => 'theirs']);

        $response = $this->send("admin/page/{$page->id}/block/{$mine->id}/item");

        $this->assertSame(['mine'], array_column($response->json('data.rows'), 'title'));
    }

    public function test_a_nested_insert_takes_the_block_from_the_route(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        $response = $this->send("admin/page/{$page->id}/block/{$block->id}/item/insert", [
            'title' => 'First',
            'enable_time' => null,
            'disable_time' => null
        ]);

        $this->assertSame($block->id, BlockItem::query()->findOrFail(intval($response->json('data.id')))->block_id);
    }

    // Pinned so the unvalidated grandparent is not later mistaken for a regression.
    public function test_a_wrong_page_in_the_url_still_reaches_the_item(): void {
        $page = PageFactory::new()->createOne();
        $other = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $this->send("admin/page/{$other->id}/block/{$block->id}/item/{$item->id}")->assertJsonPath('success', true);
    }

    public function test_a_nested_get_cannot_reach_another_blocks_item(): void {
        $page = PageFactory::new()->createOne();
        $mine = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $theirs = BlockFactory::new()->createOne(['page_id' => $page->id]);

        $item = BlockItemFactory::new()->createOne(['block_id' => $theirs->id]);

        $this->send("admin/page/{$page->id}/block/{$mine->id}/item/{$item->id}")
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_the_breadcrumb_walks_all_three_levels(): void {
        $page = PageFactory::new()->createOne(['title' => 'About']);
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'title' => 'Hero']);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id, 'title' => 'Slide']);

        $labels = array_column($this->send("admin/page/{$page->id}/block/{$block->id}/item/{$item->id}")->json('data.breadcrumbs'), 'label');

        $this->assertContains('About', $labels);
        $this->assertContains('Hero', $labels);
        $this->assertContains('Slide', $labels);
    }

    public function test_the_breadcrumb_resolves_every_placeholder(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $item = BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        foreach ($this->send("admin/page/{$page->id}/block/{$block->id}/item/{$item->id}")->json('data.breadcrumbs') as $crumb) {
            $this->assertStringNotContainsString('{', strval(array_get_value($crumb, 'path')));
        }
    }

    public function test_the_listing_breadcrumb_resolves_every_placeholder(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        foreach ($this->send("admin/page/{$page->id}/block/{$block->id}/item")->json('data.breadcrumbs') as $crumb) {
            $this->assertStringNotContainsString('{', strval(array_get_value($crumb, 'path')));
        }
    }

}
