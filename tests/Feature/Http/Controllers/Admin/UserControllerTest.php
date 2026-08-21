<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\ExportableUserController;

class UserControllerTest extends FeatureTestCase {

    private const ADMIN = 500;
    private const REGULAR = 5000;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(fn () => Route::prefix('exportable-user')->group(fn () => ActionRoutes::scan(ExportableUserController::class)));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array {
        return array_merge([
            'username' => 'newbie',
            'password' => null,
            'group_id' => null,
            'disabled' => false,
            'enable_time' => null,
            'disable_time' => null,
            'permissions' => null
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $token, string $uri, array $input = []): TestResponse {
        return $this->withToken($token)->postJson($uri, $input);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function login(string $username, string $password): TestResponse {
        $code = '13579';

        return $this->postJson('admin/auth/login', [
            'username' => $username,
            'password' => $password,
            'token' => $this->captcha($code),
            'code' => $code
        ]);
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function signIn(int $id, array $permissions = []): string {
        return UserFactory::new()->createOne(['id' => $id, 'permissions' => $permissions])->createToken();
    }

    public function test_a_valid_token_reaches_the_listing_through_both_middlewares(): void {
        $this->send($this->signIn(User::ROOT), 'admin/user')->assertJsonPath('success', true);
    }

    public function test_the_listing_joins_the_group_title_and_labels_the_boolean(): void {
        $group = GroupFactory::new()->createOne(['title' => 'Editors']);

        UserFactory::new()->createOne(['username' => 'zoe', 'group_id' => $group->id, 'disabled' => true]);

        $response = $this->send($this->signIn(User::ROOT), 'admin/user');
        $rows = array_column($response->json('data.rows'), 'group_title', 'username');

        $this->assertSame('Editors', $rows['zoe']);

        $response->assertJsonPath('data.columns.3.name', 'disabled');
        $response->assertJsonPath('data.columns.3.presentation', 'select');
        $response->assertJsonPath('data.columns.3.options.0.id', 1);
        $response->assertJsonPath('data.columns.3.options.0.title', 'Yes');
    }

    public function test_an_admin_never_sees_the_root_account(): void {
        UserFactory::new()->createOne(['id' => User::ROOT, 'username' => 'root']);

        $response = $this->send($this->signIn(self::ADMIN), 'admin/user');

        $this->assertNotContains('root', array_column($response->json('data.rows'), 'username'));
    }

    public function test_the_root_account_sees_itself(): void {
        $token = UserFactory::new()->createOne(['id' => User::ROOT, 'username' => 'root'])->createToken();

        $response = $this->send($token, 'admin/user');

        $this->assertContains('root', array_column($response->json('data.rows'), 'username'));
    }

    public function test_an_admin_cannot_read_update_or_delete_the_root_account(): void {
        UserFactory::new()->createOne(['id' => User::ROOT, 'username' => 'root']);

        $token = $this->signIn(self::ADMIN);
        $key = strval(User::ROOT);

        $this->send($token, "admin/user/{$key}")->assertJsonPath('error', 'data-not-found');
        $this->send($token, "admin/user/{$key}/update", $this->form(['username' => 'root']))->assertJsonPath('error', 'data-not-found');
        $this->send($token, 'admin/user/delete', ['id' => User::ROOT])->assertJsonPath('error', 'data-not-found');
    }

    public function test_a_user_cannot_delete_itself(): void {
        $response = $this->send($this->signIn(self::ADMIN), 'admin/user/delete', ['id' => self::ADMIN]);

        $response->assertJsonPath('code', 403);
        $response->assertJsonPath('error', 'permission-denied');
    }

    public function test_an_admin_creates_reads_updates_and_deletes_an_account(): void {
        $token = $this->signIn(self::ADMIN);
        $created = $this->send($token, 'admin/user/insert', $this->form(['password' => 'secret-Passw0rd']));
        $id = strval($created->json('data.id'));

        $created->assertJsonPath('success', true);

        $this->send($token, "admin/user/{$id}")->assertJsonPath('data.data.username', 'newbie');
        $this->send($token, "admin/user/{$id}/update", $this->form(['username' => 'renamed', 'password' => 'secret-Passw0rd']))->assertJsonPath('success', true);

        $this->assertSame('renamed', User::query()->findOrFail($id)->username);

        $this->send($token, 'admin/user/delete', ['id' => $id])->assertJsonPath('success', true);

        $this->assertNull(User::query()->find($id));
    }

    public function test_the_password_is_hashed_and_never_returned(): void {
        $token = $this->signIn(self::ADMIN);
        $id = strval($this->send($token, 'admin/user/insert', $this->form(['password' => 'secret-Passw0rd']))->json('data.id'));
        $user = User::query()->findOrFail($id);

        $this->assertNotSame('secret-Passw0rd', $user->password);
        $this->assertTrue(Hash::check('secret-Passw0rd', strval($user->password)));

        $this->send($token, "admin/user/{$id}")->assertJsonMissingPath('data.data.password');
    }

    public function test_an_admin_cannot_create_an_account_with_a_weak_password(): void {
        $token = $this->signIn(self::ADMIN);

        $response = $this->send($token, 'admin/user/insert', $this->form(['password' => 'short']));

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.password', ['regex']);
    }

    public function test_an_admin_must_send_the_password_key_when_creating_an_account(): void {
        $input = $this->form();

        unset($input['password']);

        $response = $this->send($this->signIn(self::ADMIN), 'admin/user/insert', $input);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.password', ['present']);
        $this->assertNull(User::query()->where('username', 'newbie')->first());
    }

    public function test_an_admin_cannot_replace_an_account_password_with_a_weak_one(): void {
        UserFactory::new()->createOne(['id' => 6000, 'username' => 'managed-user']);

        $token = $this->signIn(self::ADMIN);
        $response = $this->send($token, 'admin/user/6000/update', $this->form([
            'username' => 'managed-user',
            'password' => 'short'
        ]));

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.password', ['regex']);
    }

    public function test_an_admin_must_send_the_password_key_when_updating_an_account(): void {
        $user = UserFactory::new()->createOne(['id' => 6000, 'username' => 'managed-user']);
        $input = $this->form([
            'username' => 'renamed-user',
            'enable_time' => $user->enable_time?->format('Y-m-d H:i:s')
        ]);

        unset($input['password']);

        $response = $this->send($this->signIn(self::ADMIN), 'admin/user/6000/update', $input);

        $response->assertJson(['code' => 422, 'error' => 'validation-failed']);
        $response->assertJsonPath('fields.password', ['present']);
        $this->assertSame('managed-user', User::query()->findOrFail(6000)->username);
    }

    public function test_an_admin_replacing_a_password_revokes_the_account_sessions(): void {
        $user = UserFactory::new()->createOne(['id' => 6000, 'username' => 'managed-user']);
        $session = $user->createToken();

        $token = $this->signIn(self::ADMIN);

        $this->send($token, 'admin/user/6000/update', $this->form([
            'username' => 'managed-user',
            'password' => 'another-Passw0rd',
            'enable_time' => $user->enable_time?->format('Y-m-d H:i:s')
        ]))->assertJsonPath('success', true);

        $this->withToken($session)
            ->postJson('admin/auth/profile')
            ->assertJson(['success' => false, 'error' => 'invalid-token']);
    }

    public function test_an_admin_leaving_a_password_blank_preserves_the_password_and_sessions(): void {
        $user = UserFactory::new()->createOne(['id' => 6000, 'username' => 'managed-user']);
        $session = $user->createToken();
        $token = $this->signIn(self::ADMIN);

        foreach ([null, ''] as $password) {
            $this->send($token, 'admin/user/6000/update', $this->form([
                'username' => 'managed-user',
                'password' => $password,
                'enable_time' => $user->enable_time?->format('Y-m-d H:i:s')
            ]))->assertJsonPath('success', true);

            $this->withToken($session)
                ->postJson('admin/auth/profile')
                ->assertJsonPath('data.profile.username', 'managed-user');
            $this->login('managed-user', 'secret-Passw0rd')->assertJsonPath('success', true);
        }
    }

    public function test_the_account_form_carries_the_permissions_column(): void {
        $response = $this->send($this->signIn(self::ADMIN), 'admin/user/new');

        $response->assertJsonPath('data.columns.7.name', 'permissions');
        $response->assertJsonPath('data.columns.7.presentation', 'permissions');
        $response->assertJsonPath('data.columns.7.sortable', false);
        $response->assertJsonPath('data.columns.7.options.0.id', 'authority');

        $this->assertStringContainsString('"permissions":{}', strval($response->getContent()));
    }

    public function test_a_personal_grant_written_through_the_form_takes_effect(): void {
        $token = $this->signIn(self::ADMIN);
        $id = strval($this->send($token, 'admin/user/insert', $this->form(['username' => 'granted']))->json('data.id'));

        $this->send($token, "admin/user/{$id}/update", $this->form([
            'username' => 'granted',
            'permissions' => ['user' => ['query' => true], 'nowhere' => ['query' => true]]
        ]))->assertJsonPath('success', true);

        $this->assertSame(['user' => ['query' => true]], User::query()->findOrFail($id)->permissions);

        $this->send($token, "admin/user/{$id}")->assertJsonPath('data.data.permissions.user.query', true);
    }

    public function test_a_regular_user_without_a_grant_is_denied(): void {
        $response = $this->send($this->signIn(self::REGULAR), 'admin/user');

        $response->assertJsonPath('code', 403);
        $response->assertJsonPath('error', 'permission-denied');
    }

    public function test_a_regular_user_reaches_the_listing_once_granted(): void {
        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true]]);

        $this->send($token, 'admin/user')->assertJsonPath('success', true);
    }

    public function test_a_regular_user_only_sees_regular_accounts(): void {
        UserFactory::new()->createOne(['id' => 600, 'username' => 'protected-admin']);
        UserFactory::new()->createOne(['id' => 6000, 'username' => 'visible-regular']);

        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true]]);
        $response = $this->send($token, 'admin/user');
        $usernames = array_column($response->json('data.rows'), 'username');

        $this->assertNotContains('protected-admin', $usernames);
        $this->assertContains('visible-regular', $usernames);
    }

    public function test_a_regular_user_cannot_read_an_admin_account(): void {
        UserFactory::new()->createOne(['id' => self::ADMIN, 'username' => 'protected-admin']);

        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true]]);

        $this->send($token, 'admin/user/' . self::ADMIN)->assertJsonPath('error', 'data-not-found');
    }

    public function test_a_regular_user_cannot_update_or_delete_an_admin_account(): void {
        UserFactory::new()->createOne(['id' => self::ADMIN, 'username' => 'protected-admin']);

        $token = $this->signIn(self::REGULAR, ['user' => ['update' => true, 'delete' => true]]);

        $this->send($token, 'admin/user/' . self::ADMIN . '/update', $this->form(['username' => 'compromised']))
            ->assertJsonPath('error', 'data-not-found');
        $this->send($token, 'admin/user/delete', ['id' => self::ADMIN])
            ->assertJsonPath('error', 'data-not-found');

        $root = $this->signIn(User::ROOT);

        $this->send($root, 'admin/user/' . self::ADMIN)->assertJsonPath('data.data.username', 'protected-admin');
    }

    public function test_a_regular_user_cannot_copy_an_admin_account(): void {
        $this->useMenuFixtures('authority');

        UserFactory::new()->createOne(['id' => self::ADMIN, 'username' => 'protected-admin']);

        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true, 'insert' => true]]);

        $this->send($token, 'admin/user/' . self::ADMIN . '/copy')->assertJsonPath('error', 'data-not-found');
    }

    public function test_a_regular_user_exports_no_admin_accounts(): void {
        UserFactory::new()->createOne(['id' => self::ADMIN, 'username' => 'protected-admin']);
        UserFactory::new()->createOne(['id' => 6000, 'username' => 'visible-regular']);

        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true]]);
        $usernames = array_column($this->send($token, 'admin/exportable-user/export')->json('data.rows'), 'username');

        $this->assertNotContains('protected-admin', $usernames);
        $this->assertContains('visible-regular', $usernames);
    }

    public function test_a_regular_user_can_manage_another_regular_account_when_granted(): void {
        UserFactory::new()->createOne(['id' => 6000, 'username' => 'visible-regular']);

        $token = $this->signIn(self::REGULAR, ['user' => ['query' => true, 'update' => true, 'delete' => true]]);

        $this->send($token, 'admin/user/6000')->assertJsonPath('data.data.username', 'visible-regular');
        $this->send($token, 'admin/user/6000/update', $this->form(['username' => 'renamed-regular']))
            ->assertJsonPath('success', true);
        $this->send($token, 'admin/user/delete', ['id' => 6000])->assertJsonPath('success', true);
        $this->send($token, 'admin/user/6000')->assertJsonPath('error', 'data-not-found');
    }

    public function test_an_admin_can_read_another_admin_account(): void {
        UserFactory::new()->createOne(['id' => 600, 'username' => 'peer-admin']);

        $token = $this->signIn(self::ADMIN);

        $this->send($token, 'admin/user/600')->assertJsonPath('data.data.username', 'peer-admin');
    }

    public function test_root_can_read_an_admin_account(): void {
        UserFactory::new()->createOne(['id' => self::ADMIN, 'username' => 'admin-account']);

        $token = $this->signIn(User::ROOT);

        $this->send($token, 'admin/user/' . self::ADMIN)->assertJsonPath('data.data.username', 'admin-account');
    }

    public function test_the_account_resource_offers_no_copy_export_or_sort(): void {
        $token = $this->signIn(User::ROOT);

        foreach (['admin/user/1/copy', 'admin/user/export', 'admin/user/sort', 'admin/user/sort/save'] as $uri) {
            $this->send($token, $uri)->assertJsonPath('error', 'permission-denied');
        }
    }

}
