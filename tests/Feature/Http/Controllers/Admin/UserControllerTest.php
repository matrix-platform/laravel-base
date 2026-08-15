<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use Tests\Factories\GroupFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class UserControllerTest extends FeatureTestCase {

    private const ADMIN = 500;
    private const REGULAR = 5000;

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

    public function test_the_account_resource_offers_no_copy_export_or_sort(): void {
        $token = $this->signIn(User::ROOT);

        foreach (['admin/user/1/copy', 'admin/user/export', 'admin/user/sort', 'admin/user/sort/save'] as $uri) {
            $this->send($token, $uri)->assertJsonPath('error', 'permission-denied');
        }
    }

}
