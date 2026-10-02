<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function banner(array $overrides = []): array {
        return ['type' => 'banner', 'title' => 'Banner', 'data__layout' => 'wide', 'data__image' => [], 'enable_time' => null, 'disable_time' => null, ...$overrides];
    }

    /**
     * @return list<array{id: int}>
     */
    private function drive(string $mime, int $size): array {
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::File;
        $node->name = str()->random(8) . '.img';
        $node->hash = 'hash-' . $node->name;
        $node->path = date('Ym') . '/' . str()->random(32);
        $node->size = $size;
        $node->mime_type = $mime;
        $node->width = 400;
        $node->height = 300;

        $node->save();

        return [['id' => $node->id]];
    }

    /**
     * @return list<int>
     */
    private function order(int $page): array {
        return array_values(array_map(intval(...), Block::query()->where('page_id', $page)->orderBy('ranking')->orderBy('id')->pluck('id')->all()));
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_every_mounted_action_answers(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id]);
        $prefix = "admin/page/{$page->id}/block";

        foreach ([$prefix, "{$prefix}/new", "{$prefix}/{$block->id}", "{$prefix}/arrange"] as $uri) {
            $this->send($uri)->assertJsonPath('success', true);
        }

        $this->send("{$prefix}/insert", ['type' => 'editor', 'title' => 'a', 'data__content__tw' => null, 'data__content__en' => null, 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
        $this->send("{$prefix}/{$block->id}/update", ['title' => 'b', 'data__content__tw' => null, 'data__content__en' => null, 'enable_time' => null, 'disable_time' => null])->assertJsonPath('success', true);
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
        $this->assertSame('Whole seconds', $seconds['hint']);
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
        $this->assertTrue($this->columnByName($columns, 'type')['locked']);
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

    public function test_the_listing_shows_the_type_the_title_the_item_count_and_the_update_time(): void {
        $page = PageFactory::new()->createOne();

        BlockFactory::new()->createOne(['page_id' => $page->id]);

        $columns = $this->send("admin/page/{$page->id}/block")->json('data.columns');

        $this->assertEqualsCanonicalizing(['type', 'title', 'items_count', 'update_time'], array_column($columns, 'name'));
    }

    public function test_the_new_form_opens_with_the_defaults_of_the_type(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->assertSame('wide', $this->send("admin/page/{$page->id}/block/new", ['type' => 'banner'])->json('data.data.data__layout'));
    }

    public function test_the_new_form_keeps_a_given_value_over_the_default(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->assertSame('narrow', $this->send("admin/page/{$page->id}/block/new", ['type' => 'banner', 'data__layout' => 'narrow'])->json('data.data.data__layout'));
    }

    public function test_a_type_outside_the_module_options_is_refused(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['type' => 'unknown']))
            ->assertJsonPath('success', false)
            ->assertJsonPath('fields.type', ['in']);

        $this->assertSame(0, Block::query()->count());
    }

    public function test_a_page_content_block_cannot_be_inserted(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", ['type' => Block::PAGE_CONTENT, 'title' => 'Content', 'enable_time' => null, 'disable_time' => null])
            ->assertJsonPath('success', false)
            ->assertJsonPath('fields.type', ['in']);

        $this->assertSame(0, Block::query()->count());
    }

    public function test_a_page_content_block_cannot_be_copied(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => Block::PAGE_CONTENT]);

        $this->send("admin/page/{$page->id}/block/{$block->id}/copy")
            ->assertJson(['success' => false, 'error' => 'page-content-locked']);

        $this->assertSame([$block->id], $this->order($page->id));
    }

    public function test_a_page_content_block_cannot_be_deleted(): void {
        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => Block::PAGE_CONTENT]);

        $this->send("admin/page/{$page->id}/block/delete", ['id' => [$block->id]])
            ->assertJson(['success' => false, 'error' => 'page-content-locked']);

        $this->assertSame([$block->id], $this->order($page->id));
    }

    public function test_a_page_content_block_can_still_be_edited(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => Block::PAGE_CONTENT]);

        $this->send("admin/page/{$page->id}/block/{$block->id}/update", ['title' => 'Renamed', 'enable_time' => null, 'disable_time' => null])
            ->assertJsonPath('success', true);

        $this->assertSame('Renamed', $block->refresh()->title);
    }

    public function test_a_select_value_outside_its_options_is_refused_on_insert(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['data__layout' => 'huge']))
            ->assertJsonPath('success', false)
            ->assertJsonPath('fields.data__layout', ['in']);

        $this->assertSame(0, Block::query()->count());
    }

    public function test_a_select_value_outside_its_options_is_refused_on_update(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $block = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'banner', 'data' => ['layout' => 'wide']]);

        $this->send("admin/page/{$page->id}/block/{$block->id}/update", $this->banner(['data__layout' => 'huge']))
            ->assertJsonPath('success', false)
            ->assertJsonPath('fields.data__layout', ['in']);

        $this->assertSame('wide', array_get_value($block->refresh()->data, 'layout'));
    }

    public function test_a_blank_select_value_is_left_to_the_required_rule(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['data__layout' => null]))->assertJsonPath('success', true);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function badImages(): array {
        return [
            'gif' => ['image/gif', 1024],
            'svg' => ['image/svg+xml', 1024]
        ];
    }

    #[DataProvider('badImages')]
    public function test_an_image_of_a_wrong_format_is_refused(string $mime, int $size): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['data__image' => $this->drive($mime, $size)]))
            ->assertJsonPath('success', false)
            ->assertJsonPath('fields.data__image', ['image-invalid']);

        $this->assertSame(0, Block::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function goodImages(): array {
        return [
            'jpg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp']
        ];
    }

    #[DataProvider('goodImages')]
    public function test_an_image_of_any_size_in_an_allowed_format_is_accepted(string $mime): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['data__image' => $this->drive($mime, 10 * 1024 * 1024)]))
            ->assertJsonPath('success', true);
    }

    public function test_a_kept_image_without_file_details_is_left_alone(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();

        $this->send("admin/page/{$page->id}/block/insert", $this->banner(['data__image' => [['name' => 'old.gif', 'path' => 'block/old.gif']]]))
            ->assertJsonPath('success', true);
    }

    public function test_a_copy_carries_its_items(): void {
        $this->useBlockDataFixtures();

        $page = PageFactory::new()->createOne();
        $source = BlockFactory::new()->createOne(['page_id' => $page->id, 'type' => 'gallery']);

        BlockItemFactory::new()->createOne(['block_id' => $source->id, 'title' => 'first', 'ranking' => 1]);
        BlockItemFactory::new()->createOne(['block_id' => $source->id, 'title' => 'second', 'ranking' => 2]);

        $response = $this->send("admin/page/{$page->id}/block/{$source->id}/copy");

        $response->assertJsonPath('success', true);

        $copy = Block::query()->findOrFail(intval($response->json('data.id')));

        $this->assertNull($copy->enable_time);
        $this->assertSame(['first', 'second'], $copy->items()->orderBy('ranking')->pluck('title')->all());
        $this->assertSame(2, $source->items()->count());
    }

}
