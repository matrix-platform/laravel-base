<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Http\Controllers\Admin\MenuController;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class MenuTransferTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $this->useMenuFixtures('menu-transfer base');

        $router->middleware(['locale-api', 'encrypted-api', 'envelope-api', 'user-api', 'permission-api'])
            ->prefix('admin')
            ->group(fn () => ActionRoutes::mount('menu', MenuController::class));
    }

    private function node(?int $parentId, string $title): Menu {
        $menu = new Menu();

        $menu->parent_id = $parentId;
        $menu->title__tw = $title;
        $menu->title__en = $title;
        $menu->ranking = 100;
        $menu->enable_time = now()->subDay();

        $menu->save();

        return $menu;
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_a_host_menu_that_adds_copy_only_copies_a_root_node(): void {
        $root = $this->node(null, 'root');
        $child = $this->node($root->id, 'child');

        $this->send("admin/menu/{$root->id}/copy")->assertJsonPath('success', true);
        $this->send("admin/menu/{$child->id}/copy")->assertJsonPath('error', 'data-not-found');

        $this->assertSame(1, Menu::query()->where('title__tw', 'child')->count());
    }

    public function test_a_host_menu_that_adds_export_only_exports_the_root_nodes(): void {
        $root = $this->node(null, 'root');

        $this->node($root->id, 'child');

        $titles = array_column($this->send('admin/menu/export', ['columns' => ['title']])->json('data.rows'), 'title');

        $this->assertSame(['root'], $titles);
    }

}
