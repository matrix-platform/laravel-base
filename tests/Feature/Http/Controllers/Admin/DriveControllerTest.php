<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class DriveControllerTest extends FeatureTestCase {

    private const REGULAR = 5000;

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('local');

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_without_a_token_is_refused(): void {
        $this->postJson('admin/drive/root')->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_a_regular_logged_in_user_can_use_the_drive_without_any_menu_permission(): void {
        $token = UserFactory::new()->createOne(['id' => self::REGULAR])->createToken();

        $this->withToken($token)
            ->postJson('admin/drive/root')
            ->assertJsonPath('success', true);
    }

    public function test_root_reaches_its_home_directory(): void {
        $response = $this->send('admin/drive/home');

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', User::ROOT);
    }

    public function test_creating_a_folder_and_listing_it_as_a_child_of_root(): void {
        $root = $this->send('admin/drive/root')->json('data.id');

        $this->send("admin/drive/{$root}/folder", ['name' => 'shared'])->assertJsonPath('data.name', 'shared');

        $children = $this->send("admin/drive/{$root}/children")->json('data');

        $this->assertIsArray($children);
        $this->assertSame('shared', $children[0]['name']);
    }

    public function test_uploading_and_downloading_a_file(): void {
        $root = $this->send('admin/drive/root')->json('data.id');

        $node = $this->withToken($this->token)
            ->post("admin/drive/{$root}/upload", ['file' => UploadedFile::fake()->createWithContent('note.txt', 'hello')])
            ->json('data');

        $this->assertIsArray($node);

        $download = $this->send("admin/drive/{$node['id']}/download");

        $download->assertOk();
        $this->assertSame('hello', $download->streamedContent());
    }

    public function test_downloading_a_folder_reports_not_found(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'folder'])->json('data.id');

        $this->send("admin/drive/{$folder}/download")->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_deleted_node_can_be_restored(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'folder'])->json('data.id');

        $this->send("admin/drive/{$folder}/delete")->assertJsonPath('success', true);

        $trashed = $this->send('admin/drive/trashed')->json('data');

        $this->assertIsArray($trashed);
        $this->assertSame('folder', $trashed[0]['name']);

        $this->send("admin/drive/{$folder}/restore")->assertJsonPath('success', true);
        $this->send('admin/drive/trashed')->assertJsonPath('data', []);
    }

    public function test_deleting_a_folder_does_not_touch_its_child_but_blocks_access_to_it(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'folder'])->json('data.id');
        $child = $this->send("admin/drive/{$folder}/folder", ['name' => 'child'])->json('data.id');

        $this->send("admin/drive/{$folder}/delete")->assertJsonPath('success', true);

        $this->send("admin/drive/{$child}/rename", ['name' => 'renamed'])
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->send("admin/drive/{$folder}/restore")->assertJsonPath('success', true);

        $this->send("admin/drive/{$child}/rename", ['name' => 'renamed'])->assertJsonPath('data.name', 'renamed');
    }

    public function test_path_is_queryable_even_through_a_trashed_ancestor(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'folder'])->json('data.id');
        $child = $this->send("admin/drive/{$folder}/folder", ['name' => 'child'])->json('data.id');

        $this->send("admin/drive/{$folder}/delete")->assertJsonPath('success', true);

        $names = $this->send("admin/drive/{$child}/path")->json('data.*.name');

        $this->assertSame(['root', 'folder'], $names);
    }

    public function test_get_returns_a_nodes_metadata(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'folder'])->json('data.id');

        $response = $this->send("admin/drive/{$folder}");

        $response->assertJsonPath('data.name', 'folder');
        $response->assertJsonPath('data.deleted_by', null);
    }

    public function test_deleted_by_reports_the_user_who_deleted_it(): void {
        $token = UserFactory::new()->createOne(['id' => self::REGULAR])->createToken();
        $root = $this->send('admin/drive/root')->json('data.id');

        $folder = $this->withToken($token)
            ->postJson("admin/drive/{$root}/folder", ['name' => 'mine'])
            ->json('data.id');

        $this->withToken($token)->postJson("admin/drive/{$folder}/delete");

        $this->withToken($token)
            ->postJson('admin/drive/trashed')
            ->assertJsonPath('data.0.deleted_by', self::REGULAR);
    }

    public function test_getting_an_unknown_node_reports_not_found(): void {
        $this->send('admin/drive/999999')->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_non_numeric_id_reports_not_found_instead_of_matching_root(): void {
        $this->send('admin/drive/abc')->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_root_can_still_be_addressed_through_the_generic_id_route(): void {
        $root = $this->send('admin/drive/root')->json('data.id');

        $this->send("admin/drive/{$root}")->assertJsonPath('data.name', 'root');
    }

    public function test_group_is_null_for_a_user_without_a_group(): void {
        $this->send('admin/drive/group')->assertJsonPath('data', null);
    }

    public function test_renaming_a_node(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $folder = $this->send("admin/drive/{$root}/folder", ['name' => 'old-name'])->json('data.id');

        $response = $this->send("admin/drive/{$folder}/rename", ['name' => 'new-name', 'description' => 'a note']);

        $response->assertJsonPath('data.name', 'new-name');
        $response->assertJsonPath('data.description', 'a note');
    }

    public function test_uploading_an_image_reports_its_dimensions(): void {
        $root = $this->send('admin/drive/root')->json('data.id');

        $node = $this->withToken($this->token)
            ->post("admin/drive/{$root}/upload", ['file' => UploadedFile::fake()->image('photo.png', 20, 10)])
            ->json('data');

        $this->assertIsArray($node);
        $this->assertSame(20, $node['width']);
        $this->assertSame(10, $node['height']);
    }

    public function test_moving_a_node_to_a_new_parent(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $item = $this->send("admin/drive/{$root}/folder", ['name' => 'item'])->json('data.id');
        $target = $this->send("admin/drive/{$root}/folder", ['name' => 'target'])->json('data.id');

        $this->send("admin/drive/{$item}/move", ['parent_id' => $target])->assertJsonPath('data.parent_id', $target);
    }

    public function test_moving_to_a_missing_parent_reports_not_found(): void {
        $root = $this->send('admin/drive/root')->json('data.id');
        $item = $this->send("admin/drive/{$root}/folder", ['name' => 'item'])->json('data.id');

        $this->send("admin/drive/{$item}/move", ['parent_id' => 999999])->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

}
