<?php //>

namespace Tests\Feature\Services;

use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\DrivePermissionService;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class DrivePermissionServiceTest extends FeatureTestCase {

    private function anchor(int $id, string $name): DriveNode {
        $node = new DriveNode();

        $node->id = $id;
        $node->parent_id = null;
        $node->type = DriveNodeType::Root;
        $node->name = $name;

        $node->save();

        return $node;
    }

    private function child(DriveNode $parent, string $name): DriveNode {
        $node = new DriveNode();

        $node->parent_id = $parent->id;
        $node->type = DriveNodeType::Folder;
        $node->name = $name;

        $node->save();

        return $node;
    }

    private function root(): DriveNode {
        return DriveNode::query()->findOrFail(DriveNode::ROOT);
    }

    private function service(): DrivePermissionService {
        return new DrivePermissionService();
    }

    private function user(?int $groupId = null): User {
        return UserFactory::new()->createOne(['group_id' => $groupId]);
    }

    public function test_anyone_may_access_the_public_root_zone(): void {
        $item = $this->child($this->root(), 'shared');

        $this->assertTrue($this->service()->allowed($item, $this->user()));
    }

    public function test_the_owner_of_a_home_anchor_may_access_its_descendants(): void {
        $owner = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $note = $this->child($this->child($home, 'drafts'), 'note.txt');

        $this->assertTrue($this->service()->allowed($note, $owner));
    }

    public function test_someone_else_may_not_access_a_home_anchor_or_its_descendants(): void {
        $owner = $this->user();
        $stranger = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $note = $this->child($home, 'note.txt');

        $this->assertFalse($this->service()->allowed($home, $stranger));
        $this->assertFalse($this->service()->allowed($note, $stranger));
    }

    public function test_a_group_member_may_access_the_group_anchor_and_its_descendants(): void {
        $group = GroupFactory::new()->createOne();
        $member = $this->user($group->id);
        $groupFolder = $this->anchor($group->id, $group->title);
        $shared = $this->child($groupFolder, 'shared.csv');

        $this->assertTrue($this->service()->allowed($shared, $member));
    }

    public function test_someone_outside_the_group_may_not_access_the_group_anchor(): void {
        $group = GroupFactory::new()->createOne();
        $outsider = $this->user();
        $groupFolder = $this->anchor($group->id, $group->title);

        $this->assertFalse($this->service()->allowed($groupFolder, $outsider));
    }

    public function test_root_bypasses_every_zone(): void {
        $owner = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $rootUser = UserFactory::new()->createOne(['id' => User::ROOT]);

        $this->assertTrue($this->service()->allowed($home, $rootUser));
    }

    public function test_a_soft_deleted_ancestor_blocks_the_anchor_climb_for_everyone(): void {
        $owner = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $folder = $this->child($home, 'drafts');
        $note = $this->child($folder, 'note.txt');
        $rootUser = UserFactory::new()->createOne(['id' => User::ROOT]);

        $folder->delete();

        $this->assertFalse($this->service()->allowed($note->refresh(), $owner));
        $this->assertFalse($this->service()->allowed($note->refresh(), $rootUser));
    }

    public function test_root_user_is_denied_when_no_root_typed_anchor_is_reachable(): void {
        $orphan = new DriveNode();

        $orphan->parent_id = null;
        $orphan->type = DriveNodeType::Folder;
        $orphan->name = 'orphan';

        $orphan->save();

        $rootUser = UserFactory::new()->createOne(['id' => User::ROOT]);

        $this->assertFalse($this->service()->allowed($orphan, $rootUser));
        $this->assertFalse($this->service()->visible($orphan, $rootUser));
    }

    public function test_visible_tolerates_a_soft_deleted_ancestor_but_allowed_does_not(): void {
        $owner = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $folder = $this->child($home, 'drafts');
        $note = $this->child($folder, 'note.txt');

        $folder->delete();

        $this->assertFalse($this->service()->allowed($note->refresh(), $owner));
        $this->assertTrue($this->service()->visible($note->refresh(), $owner));
    }

    public function test_visible_still_denies_a_stranger_through_a_soft_deleted_ancestor(): void {
        $owner = $this->user();
        $stranger = $this->user();
        $home = $this->anchor($owner->id, $owner->username);
        $folder = $this->child($home, 'drafts');
        $note = $this->child($folder, 'note.txt');

        $folder->delete();

        $this->assertFalse($this->service()->visible($note->refresh(), $stranger));
    }

}
