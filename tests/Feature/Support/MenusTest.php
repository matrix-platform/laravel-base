<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Support\Menus;
use Tests\FeatureTestCase;

class MenusTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('crud');
    }

    private function menus(): Menus {
        return app(Menus::class);
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

    public function test_the_singleton_is_resolvable_without_an_authenticated_user(): void {
        $this->assertTrue($this->menus()->has('widget'));
    }

}
