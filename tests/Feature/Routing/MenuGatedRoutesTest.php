<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LogicException;
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

    public function test_the_permission_guard_outside_the_admin_prefix_is_refused_at_registration(): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('permission-api can only be used under the admin api prefix');

        Route::prefix('api')->middleware('permission-api')->group(fn () => ActionRoutes::mount('widget', WidgetController::class));
    }

    public function test_a_mount_wrapped_in_an_extra_prefix_under_the_admin_prefix_is_refused_at_registration(): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('mount() under the admin api prefix must be given the full path');

        Route::prefix('admin')->group(fn () => Route::prefix('shop')->group(fn () => ActionRoutes::mount('widget', WidgetController::class)));
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

    public function test_an_identifier_that_only_starts_with_an_action_name_still_reaches_the_id_route(): void {
        foreach (['12', 'deleted', 'export-1'] as $id) {
            $this->assertSame('admin/widget/{id}', Route::getRoutes()->match(request()->create("admin/widget/{$id}", 'POST'))->uri(), $id);
        }
    }

}
