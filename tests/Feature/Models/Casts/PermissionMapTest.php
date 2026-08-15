<?php //>

namespace Tests\Feature\Models\Casts;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\Group;
use Tests\Factories\GroupFactory;
use Tests\FeatureTestCase;

class PermissionMapTest extends FeatureTestCase {

    private function raw(Group $group): string {
        return strval(DB::table('base_group')->where('id', $group->id)->value('permissions'));
    }

    /**
     * @param array<string, mixed> $permissions
     */
    private function stored(array $permissions): Group {
        $group = GroupFactory::new()->createOne();

        $group->permissions = $permissions;
        $group->save();

        return $group;
    }

    public function test_a_group_without_a_grant_is_stored_as_an_empty_object(): void {
        $this->assertSame('{}', $this->raw(GroupFactory::new()->createOne()));
    }

    public function test_a_granted_action_is_stored_as_a_boolean_true(): void {
        $group = $this->stored(['user' => ['query' => true]]);

        $this->assertSame('{"user": {"query": true}}', $this->raw($group));
        $this->assertSame(['user' => ['query' => true]], $group->refresh()->permissions);
    }

    public function test_a_falsy_action_is_dropped_instead_of_being_stored(): void {
        $group = $this->stored(['user' => ['query' => true, 'update' => false, 'delete' => 0, 'insert' => null]]);

        $this->assertSame('{"user": {"query": true}}', $this->raw($group));
    }

    public function test_a_truthy_action_is_normalised_into_a_boolean_true(): void {
        $group = $this->stored(['user' => ['query' => 1, 'update' => '1']]);

        $this->assertSame(['user' => ['query' => true, 'update' => true]], $group->refresh()->permissions);
    }

    public function test_a_resource_left_without_any_granted_action_is_dropped(): void {
        $group = $this->stored(['user' => ['query' => false], 'group' => [], 'nowhere' => 'granted']);

        $this->assertSame('{}', $this->raw($group));
    }

    public function test_an_unreadable_action_written_outside_the_cast_survives_until_the_next_save(): void {
        $group = GroupFactory::new()->createOne();

        DB::table('base_group')->where('id', $group->id)->update(['permissions' => '{"user": {"query": 1}}']);

        $this->assertSame(['user' => ['query' => 1]], $group->refresh()->permissions);

        $group->permissions = ['user' => ['query' => 1]];
        $group->save();

        $this->assertSame('{"user": {"query": true}}', $this->raw($group));
    }

    public function test_normalising_a_stored_value_registers_as_a_change(): void {
        $group = GroupFactory::new()->createOne();

        DB::table('base_group')->where('id', $group->id)->update(['permissions' => '{"user": {"query": false}}']);

        $group->refresh();
        $group->permissions = ['user' => ['query' => false]];

        $this->assertTrue($group->isDirty('permissions'));
    }

    public function test_an_unchanged_grant_registers_as_no_change(): void {
        $group = $this->stored(['user' => ['query' => true, 'update' => true]]);

        $group->refresh();
        $group->permissions = ['user' => ['update' => true, 'query' => 1]];

        $this->assertFalse($group->isDirty('permissions'));
    }

}
