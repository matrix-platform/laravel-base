<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use ReflectionClass;
use ReflectionMethod;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\FailingGroupController;
use Tests\Stubs\GuardedGroupController;

class GroupControllerTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(function (): void {
                Route::prefix('failing-group')->group(fn () => ActionRoutes::scan(FailingGroupController::class));
                Route::prefix('guarded-group')->group(fn () => ActionRoutes::scan(GuardedGroupController::class));
            });
    }

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function logCount(): int {
        return ManipulationLog::query()->count();
    }

    private function raw(Group $group): string {
        return strval(DB::table('base_group')->where('id', $group->id)->value('permissions'));
    }

    /**
     * @return array<string, bool>
     */
    private function actions(Group $group, string $path): array {
        $actions = array_get_value($group->refresh()->permissions, $path);
        $actions = is_array($actions) ? $actions : [];

        ksort($actions);

        return $actions;
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function editor(array $permissions, string $uri, array $input = [], int $id = 5000): TestResponse {
        $token = UserFactory::new()->createOne(['id' => $id, 'permissions' => $permissions])->createToken();

        return $this->withToken($token)->postJson($uri, $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_the_new_payload_carries_a_permissions_column_shaped_like_the_others(): void {
        $response = $this->send('admin/group/new');

        $response->assertJsonPath('data.columns.2.name', 'permissions');
        $response->assertJsonPath('data.columns.2.presentation', 'permissions');
        $response->assertJsonPath('data.columns.2.type', 'json');
        $response->assertJsonPath('data.columns.2.title', 'Permissions');
        $response->assertJsonPath('data.columns.2.sortable', false);
        $response->assertJsonPath('data.columns.2.options.0.id', 'authority');

        $this->assertSame(array_keys($response->json('data.columns.1')), array_keys($response->json('data.columns.2')));
        $this->assertCount(14, $response->json('data.columns.2'));
    }

    public function test_the_new_payload_carries_an_empty_permission_object(): void {
        $this->assertStringContainsString('"permissions":{}', strval($this->send('admin/group/new')->getContent()));
    }

    public function test_the_get_payload_carries_the_stored_permissions(): void {
        $group = GroupFactory::new()->createOne(['permissions' => ['user' => ['query' => true]]]);

        $this->send("admin/group/{$group->id}")->assertJsonPath('data.data.permissions.user.query', true);
    }

    public function test_a_group_without_permissions_reads_as_an_empty_object(): void {
        $group = GroupFactory::new()->createOne();

        $this->assertStringContainsString('"permissions":{}', strval($this->send("admin/group/{$group->id}")->getContent()));
    }

    public function test_inserting_writes_a_non_empty_map(): void {
        $this->send('admin/group/insert', ['title' => 'Editors', 'permissions' => ['user' => ['query' => true, 'update' => true]]]);

        $group = Group::query()->where('title', 'Editors')->firstOrFail();

        $this->assertNotSame([], $group->permissions);
        $this->assertSame(['user' => ['query' => true, 'update' => true]], $group->permissions);
    }

    public function test_replaying_the_get_payload_leaves_the_permissions_untouched(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['user' => ['query' => true]]]);
        $data = $this->send("admin/group/{$group->id}")->json('data.data');

        $this->send("admin/group/{$group->id}/update", is_array($data) ? $data : [])->assertJsonPath('success', true);

        $this->assertSame(['user' => ['query' => true]], $group->refresh()->permissions);
    }

    public function test_a_reordered_but_equal_update_writes_no_log(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        $this->send("admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['group' => ['query' => true], 'user' => ['query' => true]]]);

        $before = $this->logCount();

        $this->send("admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['group' => ['query' => true], 'user' => ['query' => true]]]);

        $this->assertSame($before, $this->logCount());
    }

    public function test_clearing_the_permissions_stores_an_empty_object(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['user' => ['query' => true]]]);

        $this->send("admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => null]);

        $this->assertSame('{}', $this->raw($group));
        $this->assertStringContainsString('"permissions":{}', strval($this->send("admin/group/{$group->id}")->getContent()));
    }

    public function test_an_unknown_path_an_unknown_action_and_a_false_value_are_all_dropped(): void {
        $this->send('admin/group/insert', ['title' => 'Editors', 'permissions' => [
            'nowhere' => ['query' => true],
            'authority' => ['query' => true],
            'user' => ['export' => true, 'delete' => false, 'query' => true]
        ]]);

        $group = Group::query()->where('title', 'Editors')->firstOrFail();

        $this->assertSame(['user' => ['query' => true]], $group->permissions);
    }

    public function test_a_resource_with_no_grantable_action_is_dropped(): void {
        $this->useMenuFixtures('authority');
        $this->send('admin/guarded-group/insert', ['title' => 'Editors', 'permissions' => [
            'console' => ['system' => true],
            'preference' => ['user' => true],
            'report' => ['export' => true],
            'user' => ['query' => true]
        ]]);

        $group = Group::query()->where('title', 'Editors')->firstOrFail();

        $this->assertSame(['user' => ['query' => true]], $group->permissions);
    }

    public function test_a_host_guard_does_not_replace_the_whitelist(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        $this->send("admin/guarded-group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['nowhere' => ['query' => true], 'user' => ['query' => true]]]);

        $group->refresh();

        $this->assertSame('EDITORS', $group->title);
        $this->assertSame(['user' => ['query' => true]], $group->permissions);
    }

    public function test_an_editor_cannot_grant_a_permission_it_does_not_hold(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        $this->editor(['group' => ['update' => true], 'user' => ['query' => true]], "admin/group/{$group->id}/update", [
            'title' => 'Editors',
            'permissions' => ['user' => ['query' => true, 'insert' => true]]
        ])->assertJsonPath('success', true);

        $this->assertSame(['user' => ['query' => true]], $group->refresh()->permissions);
    }

    public function test_an_editor_cannot_wipe_a_permission_it_cannot_reach(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['user' => ['delete' => true]]]);

        $this->editor(['group' => ['update' => true], 'user' => ['query' => true]], "admin/group/{$group->id}/update", [
            'title' => 'Editors',
            'permissions' => ['user' => ['query' => true]]
        ])->assertJsonPath('success', true);

        $this->assertSame(['delete' => true, 'query' => true], $this->actions($group, 'user'));
    }

    public function test_an_editor_can_revoke_a_permission_it_holds(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['user' => ['query' => true, 'delete' => true]]]);

        $this->editor(['group' => ['update' => true], 'user' => ['query' => true]], "admin/group/{$group->id}/update", [
            'title' => 'Editors',
            'permissions' => []
        ])->assertJsonPath('success', true);

        $this->assertSame(['user' => ['delete' => true]], $group->refresh()->permissions);
    }

    public function test_root_may_grant_anything_on_the_tree(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        $this->send("admin/group/{$group->id}/update", [
            'title' => 'Editors',
            'permissions' => ['user' => ['query' => true, 'delete' => true, 'insert' => true, 'update' => true]]
        ]);

        $this->assertSame(['delete' => true, 'insert' => true, 'query' => true, 'update' => true], $this->actions($group, 'user'));
    }

    public function test_a_grant_made_by_another_editor_survives_a_later_edit(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        $first = ['group' => ['update' => true], 'user' => ['query' => true, 'delete' => true, 'insert' => true]];
        $second = ['group' => ['update' => true, 'query' => true, 'delete' => true], 'user' => ['insert' => true]];

        $this->editor($first, "admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['user' => ['query' => true]]], 5001);

        $this->assertSame(['query' => true], $this->actions($group, 'user'));

        $this->editor($second, "admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['group' => ['query' => true]]], 5002);

        $this->assertSame(['query' => true], $this->actions($group, 'user'));
        $this->assertSame(['query' => true], $this->actions($group, 'group'));
    }

    public function test_a_group_created_without_permissions_still_gets_an_empty_object(): void {
        $this->send('admin/group/insert', ['title' => 'Empty', 'permissions' => null]);

        $this->assertSame('{}', $this->raw(Group::query()->where('title', 'Empty')->firstOrFail()));
    }

    public function test_the_permissions_field_is_required_to_be_present_and_an_array(): void {
        $this->send('admin/group/insert', ['title' => 'Editors'])->assertJsonPath('fields.permissions', ['present']);
        $this->send('admin/group/insert', ['title' => 'Editors', 'permissions' => 'nope'])->assertJsonPath('fields.permissions', ['array']);
    }

    public function test_the_listing_does_not_project_the_permissions(): void {
        GroupFactory::new()->createOne(['title' => 'Editors']);

        $response = $this->send('admin/group');

        $response->assertJsonMissingPath('data.rows.0.permissions');
        $response->assertJsonPath('data.columns.1.required', true);
    }

    public function test_the_permission_change_lands_in_the_audit_trail(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['user' => ['query' => true]]]);

        $this->send("admin/group/{$group->id}/update", ['title' => 'Editors', 'permissions' => ['user' => ['query' => true, 'delete' => true]]]);

        $log = ManipulationLog::query()->orderByDesc('id')->firstOrFail();

        $this->assertSame(['user' => ['query' => true]], array_get_value($log->before, 'permissions'));
        $this->assertSame(['user' => ['query' => true, 'delete' => true]], array_get_value($log->after, 'permissions'));
    }

    public function test_a_copy_carries_the_unfiltered_permissions(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors', 'permissions' => ['nowhere' => ['query' => true]]]);

        $copy = $group->refresh()->replicate();

        $copy->setAttribute('title', 'Copied');
        $copy->save();

        $this->assertSame(['nowhere' => ['query' => true]], $copy->refresh()->permissions);
    }

    public function test_a_rolled_back_deletion_keeps_the_group_and_its_permissions(): void {
        $group = GroupFactory::new()->createOne(['permissions' => ['user' => ['query' => true]]]);

        $this->send('admin/failing-group/delete', ['id' => $group->id])->assertJsonPath('error', 'data-conflicted');

        $this->assertNotNull(Group::query()->find($group->id));
        $this->assertSame(['user' => ['query' => true]], $group->refresh()->permissions);
    }

    public function test_deleting_removes_the_group(): void {
        $group = GroupFactory::new()->createOne(['permissions' => ['user' => ['query' => true]]]);

        $this->send('admin/group/delete', ['id' => $group->id])->assertJsonPath('success', true);

        $this->assertNull(Group::query()->find($group->id));
    }

    public function test_the_inherited_actions_keep_their_routes_without_redeclaring_the_attribute(): void {
        $paths = array_column(ActionRoutes::resolve(FailingGroupController::class), 'path');
        $checked = 0;

        foreach (['', '{id}', '{id}/update', 'delete', 'insert', 'new'] as $path) {
            $this->assertContains($path, $paths);
        }

        foreach ((new ReflectionClass(FailingGroupController::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === FailingGroupController::class) {
                $this->assertSame([], $method->getAttributes(Action::class), $method->getName());

                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
    }

}
