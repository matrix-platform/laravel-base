<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Models\User;
use Tests\Factories\BlockFactory;
use Tests\Factories\BlockItemFactory;
use Tests\Factories\PageFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class BlockControllerTest extends FeatureTestCase {

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
        $prefix = "admin/page/{$page->id}/block";

        foreach ([$prefix, "{$prefix}/new", "{$prefix}/{$block->id}", "{$prefix}/arrange"] as $uri) {
            $this->send($uri)->assertJsonPath('success', true);
        }

        $this->send("{$prefix}/insert", ['type' => 'editor', 'title' => 'a', 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
        $this->send("{$prefix}/{$block->id}/update", ['title' => 'b', 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
        $this->send("{$prefix}/arrange/save", ['enabled' => []])->assertJsonPath('success', true);
        $this->send("{$prefix}/delete", ['id' => [$block->id]])->assertJsonPath('success', true);
    }

    public function test_the_new_form_locks_the_type_it_was_opened_with(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $response = $this->send("admin/page/{$page->id}/block/new", ['type' => 'editor']);
        $type = $this->columnByName($response->json('data.columns'), 'type');

        $this->assertFalse($type['writable']);
        $this->assertTrue($type['required']);
        $this->assertSame('editor', $response->json('data.data.type'));
    }

    public function test_the_new_form_expands_the_module_subfields_into_ordinary_columns(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $names = array_column($this->send("admin/page/{$page->id}/block/new", ['type' => 'editor'])->json('data.columns'), 'name');

        $this->assertContains('data__content', $names);
        $this->assertNotContains('data', $names);
    }

    public function test_a_module_bundle_overrides_the_shared_subfield_titles(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $columns = $this->send("admin/page/{$page->id}/block/new", ['type' => 'gallery'])->json('data.columns');
        $seconds = $this->columnByName($columns, 'data__seconds');

        $this->assertSame('Slide seconds', $seconds['title']);
        $this->assertSame('Gallery only', $seconds['remark']);
    }

    public function test_a_module_without_a_bundle_keeps_the_shared_subfield_title(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $columns = $this->send("admin/page/{$page->id}/block/new", ['type' => 'editor'])->json('data.columns');

        $this->assertSame('Content', $this->columnByName($columns, 'data__content')['title']);
    }

    public function test_without_a_configured_module_the_form_carries_no_subfields(): void {
        $page = PageFactory::new()->createOne();

        $names = array_column($this->send("admin/page/{$page->id}/block/new")->json('data.columns'), 'name');

        $this->assertNotContains('data', $names);
        $this->assertContains('type', $names);
        $this->assertContains('title', $names);
    }

    // The type picker reads the choices off the listing column, so it has to be locked here too, not only in `$inserts`.
    public function test_the_listing_shows_the_type_locked_with_its_options_and_never_the_data_column(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $columns = $this->send("admin/page/{$page->id}/block")->json('data.columns');

        $this->assertNotContains('data', array_column($columns, 'name'));
        $this->assertNotEmpty($this->columnByName($columns, 'type')['options']);
        $this->assertFalse($this->columnByName($columns, 'type')['writable']);
    }

    public function test_a_nested_list_is_scoped_to_its_page(): void {
        $mine = PageFactory::new()->createOne();
        $theirs = PageFactory::new()->createOne();

        BlockFactory::new()->createOne(['page_id' => $mine->id, 'title' => 'mine']);
        BlockFactory::new()->createOne(['page_id' => $theirs->id, 'title' => 'theirs']);

        $response = $this->send("admin/page/{$mine->id}/block");

        $this->assertSame(['mine'], array_column($response->json('data.rows'), 'title'));
    }

    public function test_a_nested_insert_takes_the_page_from_the_route_and_the_type_from_the_body(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $response = $this->send("admin/page/{$page->id}/block/insert", [
            'type' => 'editor',
            'title' => 'Hero',
            'data__content__tw' => '<p>TW</p>',
            'data__content__en' => '<p>EN</p>',
            'enable_time' => null,
            'disable_time' => null
        ]);

        $block = Block::query()->findOrFail(intval($response->json('data.id')));

        $this->assertSame($page->id, $block->page_id);
        $this->assertSame('editor', $block->type);
        $this->assertSame('<p>TW</p>', array_get_value(array_get_value($block->data, 'content'), 'tw'));
        $this->assertSame('<p>EN</p>', array_get_value(array_get_value($block->data, 'content'), 'en'));
    }

    public function test_updating_cannot_change_the_type(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'editor', 'data' => ['content' => ['tw' => 'kept', 'en' => 'kept']]]);

        $this->send("admin/page/{$page->id}/block/{$block->id}/update", [
            'type' => 'gallery',
            'title' => 'a',
            'data__content__tw' => 'kept',
            'data__content__en' => 'kept',
            'enable_time' => null,
            'disable_time' => null
        ])->assertJsonPath('success', true);

        $block->refresh();

        $this->assertSame('editor', $block->type);
        $this->assertSame('kept', array_get_value(array_get_value($block->data, 'content'), 'tw'));
    }

    public function test_a_module_without_items_reports_no_count(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'editor', 'ranking' => 100]);
        BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery', 'ranking' => 200]);

        $rows = $this->send("admin/page/{$page->id}/block")->json('data.rows');

        $this->assertNull($rows[0]['items_count']);
        $this->assertSame(0, $rows[1]['items_count']);
    }

    public function test_a_nested_get_cannot_reach_another_pages_block(): void {
        $mine = PageFactory::new()->createOne();
        $theirs = PageFactory::new()->createOne();

        $block = BlockFactory::new()->createOne(['page_id' => $theirs->id]);

        $this->send("admin/page/{$mine->id}/block/{$block->id}")
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_the_breadcrumb_resolves_every_placeholder(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        foreach ($this->send("admin/page/{$page->id}/block/{$block->id}")->json('data.breadcrumbs') as $crumb) {
            $this->assertStringNotContainsString('{', strval(array_get_value($crumb, 'path')));
        }
    }

    public function test_deleting_a_block_cascades_into_its_items(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $this->send("admin/page/{$page->id}/block/delete", ['id' => [$block->id]])->assertJsonPath('success', true);

        $this->assertSame(0, Block::query()->count());
        $this->assertSame(0, BlockItem::query()->count());
    }

    // Two levels of nesting derive two `{id}` placeholders, so the path is written out by hand.
    public function test_the_block_listing_counts_its_items_over_a_hand_written_path(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        BlockItemFactory::new()->createOne(['block_id' => $block->id]);
        BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $response = $this->send("admin/page/{$page->id}/block");
        $row = $response->json('data.rows.0');

        $this->assertSame(2, array_get_value($row, 'items_count'));
        $this->assertSame($page->id, array_get_value($row, 'page_id'));
        $this->assertSame('page/{page_id}/block/{id}/item', array_get_value($this->columnByName($response->json('data.columns'), 'items_count'), 'path'));
    }

    public function test_the_page_listing_counts_its_blocks(): void {
        $page = PageFactory::new()->createOne();

        BlockFactory::new()->createOne(['page_id' => $page->id]);
        BlockFactory::new()->createOne(['page_id' => $page->id]);

        $response = $this->send('admin/page');
        $column = $this->columnByName($response->json('data.columns'), 'blocks_count');

        $this->assertSame(2, $response->json('data.rows.0.blocks_count'));
        $this->assertSame('page/{id}/block', array_get_value($column, 'path'));
    }

    public function test_deleting_a_page_cascades_into_its_blocks_and_items(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);

        BlockItemFactory::new()->createOne(['block_id' => $block->id]);

        $this->send('admin/page/delete', ['id' => [$page->id]])->assertJsonPath('success', true);

        $this->assertSame(0, Block::query()->count());
        $this->assertSame(0, BlockItem::query()->count());
    }

    // The edit form keeps the type readonly, but it still needs the options: a select with no choices renders blank
    // even though the stored value is in `data`.
    public function test_the_edit_form_keeps_the_type_readonly_but_still_carries_its_options(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'editor']);

        $response = $this->send("admin/page/{$page->id}/block/{$block->id}");
        $column = $this->columnByName($response->json('data.columns'), 'type');

        $this->assertNotEmpty($column['options']);
        $this->assertTrue($column['readonly']);
        $this->assertFalse($column['writable']);
        $this->assertSame('editor', $response->json('data.data.type'));
    }

}
