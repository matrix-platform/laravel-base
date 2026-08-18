<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\PermissionTree;
use Tests\FeatureTestCase;

class PermissionTreeTest extends FeatureTestCase {

    /**
     * @param list<Option> $options
     * @return list<int|string>
     */
    private function ids(array $options): array {
        return array_map(fn (Option $option): int|string => $option->id, $options);
    }

    /**
     * @param list<Option> $options
     */
    private function pick(array $options, int|string $id): Option {
        foreach ($options as $option) {
            if ($option->id === $id) {
                return $option;
            }
        }

        $this->fail("missing option {$id}");
    }

    /**
     * @return list<Option>
     */
    private function tree(?string $menus = null): array {
        if ($menus !== null) {
            $this->useMenuFixtures($menus);
        }

        return app(PermissionTree::class)->options();
    }

    public function test_the_tree_is_three_levels_of_options(): void {
        $section = $this->pick($this->tree('authority'), 'authority');
        $resource = $this->pick($section->children, 'user');
        $action = $this->pick($resource->children, 'query');

        $this->assertSame('Authority', $section->title);
        $this->assertSame('Accounts', $resource->title);
        $this->assertSame('Query', $action->title);
        $this->assertSame([], $action->children);
    }

    public function test_an_option_serializes_to_four_keys(): void {
        $section = $this->pick($this->tree('authority'), 'authority');
        $action = $this->pick($this->pick($section->children, 'group')->children, 'query');

        $this->assertSame(['children' => [], 'id' => 'query', 'ranking' => 0, 'title' => 'Query'], json_decode(strval(json_encode($action)), true));
    }

    public function test_the_production_menu_files_every_resource_under_its_own_section(): void {
        $tree = $this->tree();

        $this->assertSame(['authority', 'setting', 'locale'], $this->ids($tree));
        $this->assertSame(['user', 'group'], $this->ids($this->pick($tree, 'authority')->children));
        $this->assertSame(['resource/cfg'], $this->ids($this->pick($tree, 'setting')->children));
        $this->assertSame(
            ['resource/i18n', 'resource/i18n/menu', 'resource/i18n/options', 'resource/i18n/model', 'resource/i18n/template'],
            $this->ids($this->pick($tree, 'locale')->children)
        );
    }

    public function test_action_nodes_contribute_their_tags_to_the_owning_resource(): void {
        $section = $this->pick($this->tree('authority'), 'authority');

        $this->assertSame(['query', 'delete', 'insert', 'update'], $this->ids($this->pick($section->children, 'user')->children));
        $this->assertSame(['query'], $this->ids($this->pick($section->children, 'group')->children));
    }

    public function test_a_node_without_a_ranking_still_contributes_its_tag(): void {
        $section = $this->pick($this->tree('authority'), 'authority');
        $node = app(Menus::class)->node('user/delete');

        $this->assertNotNull($node);
        $this->assertNull($node->ranking);
        $this->assertContains('delete', $this->ids($this->pick($section->children, 'user')->children));
    }

    public function test_a_resource_tagged_outside_the_whitelist_offers_no_actions(): void {
        $section = $this->pick($this->tree('authority'), 'system');

        $this->assertSame([], $this->pick($section->children, 'report')->children);
    }

    public function test_a_root_only_resource_offers_no_actions(): void {
        $section = $this->pick($this->tree('authority'), 'system');

        $this->assertSame([], $this->pick($section->children, 'console')->children);
    }

    public function test_an_always_allowed_resource_offers_no_actions(): void {
        $section = $this->pick($this->tree('authority'), 'system');

        $this->assertSame([], $this->pick($section->children, 'preference')->children);
    }

    public function test_a_nested_resource_becomes_a_child_of_its_owner(): void {
        $widget = $this->pick($this->pick($this->tree('crud'), '')->children, 'widget');

        $this->assertSame(['query', 'delete', 'insert', 'update', 'widget/{widget_id}/trinket'], $this->ids($widget->children));
        $this->assertSame(['query', 'delete', 'insert', 'update'], $this->ids($this->pick($widget->children, 'widget/{widget_id}/trinket')->children));
    }

    public function test_a_resource_without_a_section_ancestor_lands_in_the_empty_section(): void {
        $section = $this->pick($this->tree('crud'), '');

        $this->assertSame('', $section->title);
        $this->assertSame(0, $section->ranking);
        $this->assertSame(['widget', 'gadget', 'gizmo'], $this->ids($section->children));
    }

    public function test_rankings_come_from_the_node_or_the_action_order(): void {
        $section = $this->pick($this->tree('authority'), 'authority');
        $resource = $this->pick($section->children, 'user');

        $this->assertSame(100, $section->ranking);
        $this->assertSame(100, $resource->ranking);
        $this->assertSame([0, 1, 2, 3], array_map(fn (Option $option): int => $option->ranking, $resource->children));
    }

    public function test_the_whitelist_keeps_only_the_grantable_actions(): void {
        $this->useMenuFixtures('authority');

        $allowed = app(PermissionTree::class)->grants();

        $this->assertSame(['query' => 0, 'delete' => 1, 'insert' => 2, 'update' => 3], $allowed['user']);
        $this->assertSame([], $allowed['console']);
        $this->assertSame([], $allowed['preference']);
        $this->assertSame([], $allowed['report']);
    }

}
