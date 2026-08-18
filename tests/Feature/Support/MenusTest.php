<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\MenuNode;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\Resources;
use Tests\FeatureTestCase;
use Tests\Stubs\TrinketController;
use Tests\Stubs\WidgetController;

class MenusTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('crud');
    }

    /**
     * @param class-string $controller
     */
    private function assertActionsHaveNodes(string $controller, string $prefix): void {
        foreach (ActionRoutes::resolve($controller) as $route) {
            $path = $route['path'] === '' ? $prefix : "{$prefix}/{$route['path']}";

            $this->assertTrue($this->menus()->has($path), "missing menu node for {$path}");
        }
    }

    private function menus(): Menus {
        return app(Menus::class);
    }

    private function rendered(MenuNode $node): bool {
        return $node->ranking !== null || $node->group || $node->tag === 'query' || str_ends_with($node->path, '/new');
    }

    public function test_a_listed_path_is_present(): void {
        $this->assertTrue($this->menus()->has('widget/{widget_id}/trinket'));
    }

    public function test_an_unlisted_path_is_absent(): void {
        $this->assertFalse($this->menus()->has('widget/{id}/trinket'));
    }

    public function test_a_node_carries_its_bundle_name(): void {
        $node = $this->menus()->node('widget');

        $this->assertNotNull($node);
        $this->assertSame('menu/crud.widget', $node->token());
        $this->assertSame('query', $node->tag);
        $this->assertTrue($node->group);
    }

    public function test_an_unknown_node_is_null(): void {
        $this->assertNull($this->menus()->node('nothing'));
    }

    public function test_nothing_is_listed_when_the_configuration_is_empty(): void {
        config()->set('matrix.admin-menus', null);

        app()->forgetInstance(Menus::class);

        $this->assertSame([], $this->menus()->bundle());
        $this->assertFalse($this->menus()->has('widget'));
    }

    public function test_resolving_the_singleton_does_not_read_the_configuration_yet(): void {
        $this->menus();

        config()->set('matrix.admin-menus', 'authority');

        $this->assertTrue($this->menus()->has('user'));
        $this->assertFalse($this->menus()->has('widget'));
    }

    public function test_every_node_in_the_bundle_has_a_translation(): void {
        foreach (array_keys($this->menus()->bundle()) as $path) {
            $node = $this->menus()->node(strval($path));

            $this->assertNotNull($node, strval($path));
            $this->assertNotSame($node->token(), i18n($node->token()), strval($path));
        }
    }

    public function test_the_production_translations_cover_exactly_the_rendered_nodes(): void {
        $this->useMenus('base');

        $rendered = [];

        foreach ($this->menus()->nodes() as $node) {
            if (!$this->rendered($node)) {
                continue;
            }

            $this->assertNotSame($node->token(), i18n($node->token()), $node->path);

            $rendered[] = $node->path;
        }

        $bundle = app(Resources::class)->getI18nBundle('menu/base');

        $this->assertCount(22, $rendered);
        $this->assertSame($rendered, array_keys($bundle === null ? [] : $bundle));
    }

    public function test_every_action_node_in_the_production_bundle_hangs_on_its_group_resource(): void {
        $this->useMenus('base');

        $checked = 0;

        foreach ($this->menus()->nodes() as $node) {
            if ($node->group || $node->tag === null) {
                continue;
            }

            $parent = $node->parent === null ? null : $this->menus()->node($node->parent);

            $this->assertNotNull($parent, $node->path);
            $this->assertTrue($parent->group, $node->path);

            $checked++;
        }

        $this->assertSame(22, $checked);
    }

    public function test_every_crud_action_has_a_menu_node(): void {
        $this->assertActionsHaveNodes(WidgetController::class, 'widget');
        $this->assertActionsHaveNodes(TrinketController::class, 'widget/{widget_id}/trinket');
    }

    public function test_the_singleton_is_resolvable_without_an_authenticated_user(): void {
        $this->assertTrue($this->menus()->has('widget'));
    }

}
