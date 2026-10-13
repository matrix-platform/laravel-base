<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\FeatureTestCase;
use Tests\Stubs\WidgetController;

class MenuGatedRoutesTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $this->useMenuFixtures('staged');

        $router->prefix('admin')->group(function () use ($router): void {
            $router->middleware(['envelope-api', 'user-api', 'permission-api'])->group(fn () => ActionRoutes::mount('widget', WidgetController::class));
            $router->middleware(['envelope-api', 'user-api'])->group(fn () => ActionRoutes::mount('open-widget', WidgetController::class));
        });
    }

    /**
     * @return list<string>
     */
    private function uris(): array {
        return array_values(array_map(fn ($route): string => $route->uri(), Route::getRoutes()->getRoutes()));
    }

    public function test_a_permission_guarded_mount_registers_only_the_actions_its_menu_lists(): void {
        $uris = $this->uris();

        $this->assertContains('admin/widget', $uris);
        $this->assertContains('admin/widget/insert', $uris);
        $this->assertContains('admin/widget/{id}/update', $uris);
        $this->assertNotContains('admin/widget/delete', $uris);
        $this->assertNotContains('admin/widget/export', $uris);
        $this->assertNotContains('admin/widget/{id}/copy', $uris);
    }

    public function test_a_mount_outside_the_permission_guard_keeps_every_action(): void {
        $uris = $this->uris();

        $this->assertContains('admin/open-widget/delete', $uris);
        $this->assertContains('admin/open-widget/export', $uris);
        $this->assertContains('admin/open-widget/{id}/copy', $uris);
    }

    public function test_an_unlisted_action_falls_through_to_endpoint_not_found(): void {
        $this->postJson('admin/widget/delete')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    // The shipped menu lists `sms-log/{id}` but not `sms-log/insert`; without the literal route registered ahead of it,
    // `{id}` would swallow the request as id = "insert".
    public function test_an_unlisted_literal_action_is_not_captured_by_the_id_route(): void {
        $this->postJson('admin/sms-log/insert')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

}
