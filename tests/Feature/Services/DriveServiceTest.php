<?php //>

namespace Tests\Feature\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\DrivePermissionService;
use MatrixPlatform\Services\Admin\DriveService;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class DriveServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('local');
    }

    private function backdate(DriveNode $node, int $days): void {
        $node->deleted_at = now()->subDays($days);

        $node->save();
    }

    private function blob(string $name, string $content): UploadedFile {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function service(): DriveService {
        return new DriveService(new DrivePermissionService());
    }

    private function tone(): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'tone') . '.wav';

        copy(__DIR__ . '/../../fixtures/media/tone.wav', $path);

        return new UploadedFile($path, 'tone.wav', null, null, true);
    }

    private function user(?int $groupId = null): User {
        return UserFactory::new()->createOne(['group_id' => $groupId]);
    }

    public function test_root_is_the_reserved_seeded_node(): void {
        $root = $this->service()->root();

        $this->assertSame(DriveNode::ROOT, $root->id);
        $this->assertNull($root->parent_id);
    }

    public function test_home_is_created_once_per_user_and_reuses_the_users_own_id(): void {
        $service = $this->service();
        $user = $this->user();
        $first = $service->home($user);
        $second = $service->home($user);

        $this->assertSame($user->id, $first->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($user->username, $first->name);
        $this->assertNull($first->parent_id);
    }

    public function test_group_is_null_without_a_group(): void {
        $this->assertNull($this->service()->group($this->user()));
    }

    public function test_group_is_created_once_per_group_and_reuses_the_groups_own_id(): void {
        $group = GroupFactory::new()->createOne();
        $service = $this->service();
        $member = $this->user($group->id);
        $first = $service->group($member);
        $second = $service->group($member);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($group->id, $first->id);
        $this->assertSame($first->id, $second->id);
        $this->assertNull($first->parent_id);
    }

    public function test_children_of_root_is_unaffected_by_home_or_group_creation(): void {
        $service = $this->service();
        $root = $service->root();

        $service->home($this->user());
        $service->group($member = $this->user(GroupFactory::new()->createOne()->id));
        $service->createFolder($root, 'shared', $this->user());

        $names = $service
            ->children($root, $member)
            ->pluck('name')
            ->all();

        $this->assertSame(['shared'], $names);
    }

    public function test_children_of_a_regular_folder_is_not_filtered_to_anchors(): void {
        $service = $this->service();
        $owner = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'drafts', $owner);

        $names = $service
            ->children($home, $owner)
            ->pluck('name')
            ->all();

        $this->assertSame(['drafts'], $names);
        $this->assertSame($home->id, $folder->parent_id);
    }

    public function test_create_folder_rejects_a_colliding_name(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $service->createFolder($root, 'shared', $owner);

        $this->refuses('name-already-exists', fn () => $service->createFolder($root, 'shared', $owner));
    }

    public function test_upload_auto_renames_on_collision(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $service->upload($root, $this->blob('report.pdf', 'first'), $owner);
        $second = $service->upload($root, $this->blob('report.pdf', 'second'), $owner);

        $this->assertSame('report (1).pdf', $second->name);
    }

    public function test_uploading_an_image_records_its_dimensions(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $node = $service->upload($root, UploadedFile::fake()->image('photo.png', 20, 10), $owner);

        $this->assertSame(20, $node->width);
        $this->assertSame(10, $node->height);
    }

    public function test_uploading_audio_records_its_duration(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $node = $service->upload($root, $this->tone(), $owner);

        $this->assertSame(2, $node->seconds);
    }

    public function test_uploading_a_plain_file_leaves_the_media_columns_empty(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $node = $service->upload($root, $this->blob('a.bin', 'content'), $owner);

        $this->assertNull($node->width);
        $this->assertNull($node->height);
        $this->assertNull($node->seconds);
    }

    public function test_rename_rejects_a_colliding_name(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $service->createFolder($root, 'a', $owner);
        $b = $service->createFolder($root, 'b', $owner);

        $this->refuses('name-already-exists', fn () => $service->rename($b, 'a', null, $owner));
    }

    public function test_move_rejects_a_colliding_name(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $service->createFolder($root, 'a', $owner);
        $target = $service->createFolder($root, 'target', $owner);
        $item = $service->createFolder($target, 'a', $owner);

        $this->refuses('name-already-exists', fn () => $service->move($item, $root, $owner));
    }

    public function test_move_rejects_moving_an_anchor(): void {
        $service = $this->service();
        $owner = $this->user();
        $home = $service->home($owner);
        $root = $service->root();

        $this->refuses('drive-anchor-immutable', fn () => $service->move($home, $root, $owner));
    }

    public function test_move_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);
        $item = $service->createFolder($home, 'private', $owner);
        $root = $service->root();

        $this->refuses('permission-denied', fn () => $service->move($item, $root, $stranger));
    }

    public function test_trash_rejects_an_anchor(): void {
        $service = $this->service();
        $owner = $this->user();
        $home = $service->home($owner);

        $this->refuses('drive-anchor-immutable', fn () => $service->trash($home, $owner));
    }

    public function test_upload_deduplicates_identical_content_when_enabled(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $first = $service->upload($root, $this->blob('a.bin', 'same-content'), $owner);
        $second = $service->upload($root, $this->blob('b.bin', 'same-content'), $owner);

        $this->assertSame($first->path, $second->path);
        $this->assertCount(1, Storage::disk('local')->allFiles('drive/' . date('Ym')));
    }

    public function test_upload_does_not_deduplicate_when_disabled(): void {
        $this->useCfg('drive', ['deduplicate' => false]);

        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $first = $service->upload($root, $this->blob('a.bin', 'same-content'), $owner);
        $second = $service->upload($root, $this->blob('b.bin', 'same-content'), $owner);

        $this->assertNotSame($first->path, $second->path);
        $this->assertCount(2, Storage::disk('local')->allFiles('drive/' . date('Ym')));
    }

    public function test_trash_does_not_touch_children(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($folder, $owner);

        $this->assertNotNull($folder->refresh()->deleted_at);
        $this->assertNull($child->refresh()->deleted_at);
    }

    public function test_a_trashed_folder_blocks_access_to_its_untouched_children(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($folder, $owner);

        $this->refuses('permission-denied', fn () => $service->rename($child, 'renamed', null, $owner));
    }

    public function test_restoring_a_folder_restores_access_to_its_untouched_children(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($folder, $owner);
        $service->restore($folder->refresh(), $owner);

        $service->rename($child, 'renamed', null, $owner);

        $this->assertSame('renamed', $child->refresh()->name);
        $this->assertNull($child->deleted_at);
    }

    public function test_restore_rejects_a_colliding_name(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);

        $service->trash($folder, $owner);
        $service->createFolder($root, 'folder', $owner);

        $this->refuses('name-already-exists', fn () => $service->restore($folder->refresh(), $owner));
    }

    public function test_restore_survives_being_trashed_more_than_thirty_days_ago(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);

        $service->trash($folder, $owner);

        $this->backdate($folder, 45);

        $service->restore($folder->refresh(), $owner);

        $this->assertNull($folder->refresh()->deleted_at);
    }

    public function test_restore_writes_a_manipulation_log_entry(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);

        $service->trash($folder, $owner);
        $service->restore($folder->refresh(), $owner);

        $log = ManipulationLog::query()
            ->where('data_type', 'base_drive_node')
            ->where('type', ManipulationType::Restored)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_trashed_defaults_to_the_last_thirty_days(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $recent = $service->createFolder($root, 'recent', $owner);
        $old = $service->createFolder($root, 'old', $owner);

        $service->trash($recent, $owner);
        $service->trash($old, $owner);

        $this->backdate($old, 45);

        $names = $service
            ->trashed($owner, null, false)
            ->pluck('name')
            ->all();

        $this->assertSame(['recent'], $names);
    }

    public function test_trashed_with_all_ignores_the_day_window(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $old = $service->createFolder($root, 'old', $owner);

        $service->trash($old, $owner);

        $this->backdate($old, 45);

        $names = $service
            ->trashed($owner, null, true)
            ->pluck('name')
            ->all();

        $this->assertSame(['old'], $names);
    }

    public function test_trashed_still_lists_an_item_whose_own_parent_is_also_trashed(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($child, $owner);
        $service->trash($folder, $owner);

        $names = $service
            ->trashed($owner, null, true)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(['folder', 'child'], $names);
    }

    public function test_restore_still_refuses_a_child_while_its_parent_stays_trashed(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($child, $owner);
        $service->trash($folder, $owner);

        $this->refuses('permission-denied', fn () => $service->restore($child->refresh(), $owner));
    }

    public function test_a_rollback_removes_the_file_that_was_just_written(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $stored = null;

        $this->refuses('request-failed', function () use ($service, $root, $owner, &$stored): void {
            DB::transaction(function () use ($service, $root, $owner, &$stored): void {
                $stored = $service->upload($root, $this->blob('a.bin', 'content'), $owner)->path;

                error('request-failed');
            });
        });

        $this->assertNotNull($stored);
        Storage::disk('local')->assertMissing("drive/{$stored}");
    }

    public function test_move_relocates_a_node_between_folders(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $item = $service->createFolder($root, 'item', $owner);
        $target = $service->createFolder($root, 'target', $owner);

        $service->move($item, $target, $owner);

        $this->assertSame($target->id, $item->refresh()->parent_id);
    }

    public function test_move_rejects_a_circular_destination(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $a = $service->createFolder($root, 'a', $owner);
        $b = $service->createFolder($a, 'b', $owner);

        $this->refuses('invalid-move-target', fn () => $service->move($a, $b, $owner));
    }

    public function test_move_allows_an_unrelated_destination_elsewhere_in_the_tree(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $a = $service->createFolder($root, 'a', $owner);
        $b = $service->createFolder($root, 'b', $owner);
        $c = $service->createFolder($b, 'c', $owner);

        $service->move($a, $c, $owner);

        $this->assertSame($c->id, $a->refresh()->parent_id);
    }

    public function test_rename_updates_the_name(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'old-name', $owner);

        $service->rename($folder, 'new-name', 'a fresh description', $owner);

        $refreshed = $folder->refresh();

        $this->assertSame('new-name', $refreshed->name);
        $this->assertSame('a fresh description', $refreshed->description);
    }

    public function test_rename_clears_the_description_when_none_is_given(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);

        $service->rename($folder, 'folder', 'first description', $owner);
        $service->rename($folder, 'folder', null, $owner);

        $this->assertNull($folder->refresh()->description);
    }

    public function test_children_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);

        $this->refuses('permission-denied', fn () => $service->children($home, $stranger));
    }

    public function test_create_folder_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);

        $this->refuses('permission-denied', fn () => $service->createFolder($home, 'nope', $stranger));
    }

    public function test_upload_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);

        $this->refuses('permission-denied', fn () => $service->upload($home, $this->blob('a.bin', 'content'), $stranger));
    }

    public function test_rename_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'private', $owner);

        $this->refuses('permission-denied', fn () => $service->rename($folder, 'renamed', null, $stranger));
    }

    public function test_trash_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'private', $owner);

        $this->refuses('permission-denied', fn () => $service->trash($folder, $stranger));
    }

    public function test_restore_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'private', $owner);

        $service->trash($folder, $owner);

        $this->refuses('permission-denied', fn () => $service->restore($folder->refresh(), $stranger));
    }

    public function test_move_rejects_the_root(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'somewhere', $owner);

        $this->refuses('drive-anchor-immutable', fn () => $service->move($root, $folder, $owner));
    }

    public function test_trash_rejects_the_root(): void {
        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();

        $this->refuses('drive-anchor-immutable', fn () => $service->trash($root, $owner));
    }

    public function test_deleted_by_is_null_for_a_node_that_is_not_trashed(): void {
        $service = $this->service();
        $root = $service->root();
        $folder = $service->createFolder($root, 'folder', $this->user());

        $this->assertNull($service->deletedBy($folder));
    }

    public function test_deleted_by_reports_the_acting_users_id(): void {
        $this->actAsRoot();

        $service = $this->service();
        $root = $service->root();
        $owner = $this->user();
        $folder = $service->createFolder($root, 'folder', $owner);

        $service->trash($folder, $owner);

        $this->assertSame(User::ROOT, $service->deletedBy($folder->refresh()));
    }

    public function test_path_returns_the_ancestor_chain_from_the_anchor_down(): void {
        $service = $this->service();
        $owner = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'folder', $owner);
        $note = $service->createFolder($folder, 'note', $owner);

        $names = $service
            ->path($note, $owner)
            ->pluck('name')
            ->all();

        $this->assertSame([$owner->username, 'folder'], $names);
    }

    public function test_path_is_still_queryable_when_an_ancestor_is_trashed(): void {
        $service = $this->service();
        $owner = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'folder', $owner);
        $child = $service->createFolder($folder, 'child', $owner);

        $service->trash($folder, $owner);

        $names = $service
            ->path($child, $owner)
            ->pluck('name')
            ->all();

        $this->assertSame([$owner->username, 'folder'], $names);
    }

    public function test_path_denies_a_stranger(): void {
        $service = $this->service();
        $owner = $this->user();
        $stranger = $this->user();
        $home = $service->home($owner);
        $folder = $service->createFolder($home, 'folder', $owner);

        $this->refuses('permission-denied', fn () => $service->path($folder, $stranger));
    }

}
