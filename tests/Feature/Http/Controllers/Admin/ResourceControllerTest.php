<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\PinnedResourceController;

class ResourceControllerTest extends FeatureTestCase {

    private string $admin;

    private string $root;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api', 'permission-api'])
            ->prefix('admin')
            ->group(fn () => Route::prefix('mail-setting')->group(fn () => ActionRoutes::scan(PinnedResourceController::class)));
    }

    protected function setUp(): void {
        parent::setUp();

        $this->useResourceFixtures();
        $this->useMenus('base resource');

        $this->useResourceWhitelist([
            'cfg' => ['admin', 'dotted'],
            'i18n' => ['errors'],
            'i18n/options' => ['color'],
            'i18n/template' => ['greeting']
        ]);

        $this->root = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
        $this->admin = UserFactory::new()->createOne(['id' => 20])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function as(string $token, string $uri, array $input = []): TestResponse {
        return $this->withToken($token)->postJson($uri, $input);
    }

    /**
     * @return list<string>
     */
    private function ids(string $token, string $group = 'cfg'): array {
        return array_map(strval(...), array_column($this->as($token, "admin/resource/{$group}")->json('data.rows'), 'id'));
    }

    public function test_the_listing_needs_a_session(): void {
        $this->postJson('admin/resource/cfg')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 401]);
    }

    public function test_every_shipped_group_has_its_own_endpoint(): void {
        $this->assertContains('admin', $this->ids($this->admin, 'cfg'));
        $this->assertContains('errors', $this->ids($this->admin, 'i18n'));
        $this->assertContains('color', $this->ids($this->admin, 'i18n/options'));
        $this->assertContains('greeting', $this->ids($this->admin, 'i18n/template'));
        $this->assertContains('base', $this->ids($this->root, 'i18n/menu'));
        $this->assertContains('base_user', $this->ids($this->root, 'i18n/model'));
    }

    public function test_each_group_carries_its_own_menu_node(): void {
        $this->assertSame('Menus', $this->as($this->root, 'admin/resource/i18n/menu')->json('data.title'));
        $this->assertSame('Messages', $this->as($this->root, 'admin/resource/i18n')->json('data.title'));
        $this->assertSame('General', $this->as($this->root, 'admin/resource/cfg')->json('data.title'));
    }

    public function test_an_ordinary_administrator_sees_only_the_whitelisted_bundles(): void {
        $ids = $this->ids($this->admin);

        $this->assertContains('admin', $ids);
        $this->assertNotContains('secret', $ids);
        $this->assertNotContains('gmail', $ids);
    }

    public function test_the_root_account_sees_every_file_in_the_group(): void {
        $ids = $this->ids($this->root);

        $this->assertContains('secret', $ids);
        $this->assertContains('gmail', $ids);
        $this->assertContains('action-copy', $ids);
    }

    public function test_a_group_without_a_whitelist_is_empty_for_an_administrator(): void {
        $this->assertSame([], $this->ids($this->admin, 'i18n/menu'));
        $this->assertNotSame([], $this->ids($this->root, 'i18n/menu'));
    }

    public function test_a_whitelisted_name_without_a_file_is_not_listed(): void {
        $this->useResourceWhitelist(['cfg' => ['admin', 'does-not-exist']]);

        $this->assertSame(['admin'], $this->ids($this->admin));
    }

    public function test_a_subdirectory_of_a_group_is_not_a_bundle(): void {
        $ids = $this->ids($this->root, 'i18n');

        $this->assertContains('errors', $ids);
        $this->assertNotContains('menu', $ids);
        $this->assertNotContains('options', $ids);
    }

    public function test_a_bundle_outside_the_whitelist_is_missing_for_an_administrator(): void {
        $this->as($this->admin, 'admin/resource/cfg/get', ['name' => 'secret'])
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404]);

        $this->as($this->root, 'admin/resource/cfg/get', ['name' => 'secret'])
            ->assertOk()
            ->assertJsonPath('data.id', 'cfg/secret');
    }

    public function test_a_bundle_outside_the_whitelist_cannot_be_written_by_an_administrator(): void {
        $this->as($this->admin, 'admin/resource/cfg/update', ['name' => 'secret', 'data' => ['token' => 'stolen']])
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404]);

        $this->assertSame(0, ResourceOverride::query()->count());
    }

    public function test_a_bundle_of_another_group_is_unreachable_from_this_one(): void {
        $this->as($this->root, 'admin/resource/i18n/get', ['name' => 'admin'])
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404]);
    }

    public function test_a_row_carries_its_label_and_its_override_count(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600, 'token-idle-minutes' => 90]]);

        $rows = array_column($this->as($this->admin, 'admin/resource/cfg')->json('data.rows'), null, 'id');

        $this->assertSame('Dotted keys', $rows['dotted']['name']);
        $this->assertSame(2, $rows['admin']['overrides']);
        $this->assertSame(0, $rows['dotted']['overrides']);
    }

    public function test_a_listing_names_the_urls_of_its_own_group(): void {
        $actions = $this->as($this->admin, 'admin/resource/i18n/options')->json('data.actions.row');

        $this->assertSame(['resource/i18n/options/get'], array_column($actions, 'url'));
    }

    public function test_an_update_round_trips_through_the_endpoint(): void {
        $response = $this->as($this->admin, 'admin/resource/cfg/update', ['name' => 'admin', 'data' => ['captcha-ttl' => 600]]);

        $response->assertOk()->assertJsonPath('data.data.captcha-ttl', 600);

        $this->assertSame(['captcha-ttl' => 600], ResourceOverride::query()->where('bundle', 'cfg/admin')->value('data'));
    }

    public function test_clearing_a_field_through_the_endpoint_drops_the_override(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600, 'token-idle-minutes' => 90]]);

        $this->as($this->admin, 'admin/resource/cfg/update', ['name' => 'admin', 'data' => ['captcha-ttl' => '']])
            ->assertOk()
            ->assertJsonPath('data.data', ['token-idle-minutes' => 90]);

        $this->assertSame(['token-idle-minutes' => 90], ResourceOverride::query()->where('bundle', 'cfg/admin')->value('data'));
    }

    public function test_a_rejected_value_answers_422_and_writes_nothing(): void {
        $this->as($this->admin, 'admin/resource/cfg/update', ['name' => 'admin', 'data' => ['captcha-ttl' => 'nope']])
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 422]);

        $this->assertSame(0, ResourceOverride::query()->count());
    }

    public function test_a_pinned_function_ignores_the_whitelist(): void {
        $this->as($this->admin, 'admin/mail-setting/get')
            ->assertOk()
            ->assertJsonPath('data.id', 'cfg/gmail');

        $this->as($this->admin, 'admin/mail-setting/update', ['data' => ['from-name' => 'Support']])->assertOk();

        $this->assertSame(['from-name' => 'Support'], ResourceOverride::query()->where('bundle', 'cfg/gmail')->value('data'));
    }

    public function test_a_pinned_function_does_not_open_the_generic_route(): void {
        $this->as($this->admin, 'admin/resource/cfg/get', ['name' => 'gmail'])
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404]);
    }

    public function test_a_pinned_function_only_answers_for_the_bundle_it_carries(): void {
        $this->as($this->admin, 'admin/mail-setting/update', ['name' => 'admin', 'data' => ['from-name' => 'Support']])->assertOk();

        $this->assertSame(['cfg/gmail'], ResourceOverride::query()->pluck('bundle')->all());
    }

    public function test_a_pinned_function_names_its_own_urls(): void {
        $actions = $this->as($this->admin, 'admin/mail-setting/get')->json('data.actions');

        $this->assertSame(['mail-setting/update'], array_column($actions, 'url'));
    }

}
