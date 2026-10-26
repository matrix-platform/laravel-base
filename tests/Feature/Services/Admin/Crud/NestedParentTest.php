<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ImportService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\NewService;
use MatrixPlatform\Services\Admin\Crud\SortService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\Relic;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class NestedParentTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label')));
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', 'title', 'relic')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
    }

    private function expectNotFound(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('data-not-found');
    }

    /**
     * @param array<string, mixed> $response
     */
    private function label(array $response): mixed {
        $data = array_get_value($response, 'data');

        return is_array($data) ? array_get_value($data, 'label') : null;
    }

    /**
     * @return array{Relic, Widget, Trinket}
     */
    private function tree(string $label): array {
        $relic = Relic::forceCreate(['label' => $label]);
        $widget = Widget::forceCreate(['title' => $label, 'relic_id' => $relic->id]);
        $trinket = Trinket::forceCreate(['label' => $label, 'widget_id' => $widget->id]);

        return [$relic, $widget, $trinket];
    }

    public function test_an_update_cannot_reach_a_row_under_another_parent(): void {
        [$relic, $mine] = $this->tree('mine');
        [, , $theirs] = $this->tree('theirs');

        try {
            (new UpdateService(Trinket::class))
                ->columns(['label'])
                ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
                ->update($theirs->id, ['label' => 'hijacked']);

            $this->fail('the update reached another parent');
        } catch (ModelNotFoundException) {
            $this->assertSame('theirs', $theirs->refresh()->label);
        }
    }

    public function test_an_update_cannot_move_a_row_to_another_parent(): void {
        [$relic, $mine, $trinket] = $this->tree('mine');
        [, $theirs] = $this->tree('theirs');

        (new UpdateService(Trinket::class))
            ->columns(['label', 'widget_id'])
            ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
            ->update($trinket->id, ['label' => 'renamed', 'widget_id' => (string) $theirs->id]);

        $trinket->refresh();

        $this->assertSame('renamed', $trinket->label);
        $this->assertSame($mine->id, $trinket->widget_id);
    }

    public function test_a_standalone_update_can_move_a_row_to_another_parent(): void {
        [, , $trinket] = $this->tree('mine');
        [, $theirs] = $this->tree('theirs');

        (new UpdateService(Trinket::class))
            ->standalone(true)
            ->columns(['label', 'widget_id'])
            ->update($trinket->id, ['label' => 'moved', 'widget_id' => (string) $theirs->id]);

        $this->assertSame($theirs->id, $trinket->refresh()->widget_id);
    }

    public function test_a_movable_update_moves_a_row_to_another_parent(): void {
        [$relic, $mine, $trinket] = $this->tree('mine');
        [, $theirs] = $this->tree('theirs');

        (new UpdateService(Trinket::class))
            ->movable(true)
            ->columns(['label', 'widget_id'])
            ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
            ->update($trinket->id, ['label' => 'moved', 'widget_id' => (string) $theirs->id]);

        $this->assertSame($theirs->id, $trinket->refresh()->widget_id);
    }

    public function test_a_movable_update_refuses_a_parent_that_does_not_exist(): void {
        [$relic, $mine, $trinket] = $this->tree('mine');

        try {
            (new UpdateService(Trinket::class))
                ->movable(true)
                ->columns(['label', 'widget_id'])
                ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
                ->update($trinket->id, ['label' => 'moved', 'widget_id' => '999999']);

            $this->fail('the update moved the row under a missing parent');
        } catch (ServiceException $exception) {
            $this->assertSame(['fields' => ['widget_id' => ['invalid-parent']]], $exception->getExtra());
        }

        $this->assertSame($mine->id, $trinket->refresh()->widget_id);
    }

    public function test_a_movable_update_refuses_a_parent_in_the_trash(): void {
        [$relic, $widget] = $this->tree('mine');
        [$trashed] = $this->tree('trashed');

        $trashed->delete();

        try {
            (new UpdateService(Widget::class))
                ->movable(true)
                ->columns(['title', 'relic_id'])
                ->params(['relic_id' => $relic->id])
                ->update($widget->id, ['title' => 'moved', 'relic_id' => (string) $trashed->id]);

            $this->fail('the update moved the row under a trashed parent');
        } catch (ServiceException $exception) {
            $this->assertSame(['fields' => ['relic_id' => ['invalid-parent']]], $exception->getExtra());
        }

        $this->assertSame($relic->id, $widget->refresh()->relic_id);
    }

    public function test_a_movable_update_refuses_to_detach_a_row_from_every_parent(): void {
        [$relic, $widget] = $this->tree('mine');

        try {
            (new UpdateService(Widget::class))
                ->movable(true)
                ->columns(['title', 'relic_id'])
                ->params(['relic_id' => $relic->id])
                ->update($widget->id, ['title' => 'moved', 'relic_id' => null]);

            $this->fail('the update detached the row from every parent');
        } catch (ServiceException $exception) {
            $this->assertSame(['fields' => ['relic_id' => ['invalid-parent']]], $exception->getExtra());
        }

        $this->assertSame($relic->id, $widget->refresh()->relic_id);
    }

    public function test_a_movable_update_refuses_to_move_a_node_under_itself_or_its_descendant(): void {
        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label', 'relic')));

        $root = Relic::forceCreate(['label' => 'root']);
        $node = Relic::forceCreate(['label' => 'node', 'relic_id' => $root->id]);
        $child = Relic::forceCreate(['label' => 'child', 'relic_id' => $node->id]);
        $grandchild = Relic::forceCreate(['label' => 'grandchild', 'relic_id' => $child->id]);

        foreach ([$node, $grandchild] as $target) {
            try {
                (new UpdateService(Relic::class))
                    ->movable(true)
                    ->columns(['label', 'relic_id'])
                    ->params(['relic_id' => $root->id])
                    ->update($node->id, ['label' => 'moved', 'relic_id' => (string) $target->id]);

                $this->fail("the update moved the node under {$target->label}");
            } catch (ServiceException $exception) {
                $this->assertSame(['fields' => ['relic_id' => ['invalid-parent']]], $exception->getExtra());
            }
        }

        $this->assertSame($root->id, $node->refresh()->relic_id);
    }

    public function test_a_movable_update_moves_a_node_under_an_unrelated_branch(): void {
        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label', 'relic')));

        $root = Relic::forceCreate(['label' => 'root']);
        $node = Relic::forceCreate(['label' => 'node', 'relic_id' => $root->id]);
        $sibling = Relic::forceCreate(['label' => 'sibling', 'relic_id' => $root->id]);

        (new UpdateService(Relic::class))
            ->movable(true)
            ->columns(['label', 'relic_id'])
            ->params(['relic_id' => $root->id])
            ->update($node->id, ['label' => 'moved', 'relic_id' => (string) $sibling->id]);

        $this->assertSame($sibling->id, $node->refresh()->relic_id);
    }

    public function test_a_movable_update_still_cannot_reach_a_row_under_another_parent(): void {
        [$relic, $mine] = $this->tree('mine');
        [, , $theirs] = $this->tree('theirs');

        try {
            (new UpdateService(Trinket::class))
                ->movable(true)
                ->columns(['label', 'widget_id'])
                ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
                ->update($theirs->id, ['label' => 'hijacked', 'widget_id' => (string) $mine->id]);

            $this->fail('the update reached another parent');
        } catch (ModelNotFoundException) {
            $this->assertSame('theirs', $theirs->refresh()->label);
        }
    }

    public function test_a_batch_delete_is_refused_when_any_id_belongs_to_another_parent(): void {
        [$relic, $mine, $own] = $this->tree('mine');
        [, , $theirs] = $this->tree('theirs');

        try {
            (new DeleteService(Trinket::class))
                ->params(['relic_id' => $relic->id, 'widget_id' => $mine->id])
                ->delete(['id' => [$own->id, $theirs->id]]);

            $this->fail('the delete reached another parent');
        } catch (ServiceException $exception) {
            $this->assertSame('data-not-found', $exception->getMessage());
            $this->assertSame(2, Trinket::query()->whereKey([$own->id, $theirs->id])->count());
        }
    }

    public function test_a_missing_parent_is_not_found_when_listing(): void {
        [$relic] = $this->tree('mine');

        $this->expectNotFound();

        (new ListService(Trinket::class))
            ->columns(['label'])
            ->params(['relic_id' => $relic->id, 'widget_id' => 999999])
            ->list([]);
    }

    public function test_a_missing_parent_is_not_found_on_the_new_form(): void {
        [$relic] = $this->tree('mine');

        $this->expectNotFound();

        (new NewService(Trinket::class))
            ->columns(['label'])
            ->params(['relic_id' => $relic->id, 'widget_id' => 999999])
            ->new();
    }

    public function test_a_missing_parent_refuses_an_insert(): void {
        [$relic] = $this->tree('mine');
        $before = Trinket::query()->count();

        try {
            (new InsertService(Trinket::class))
                ->columns(['label'])
                ->params(['relic_id' => $relic->id, 'widget_id' => 999999])
                ->insert(['label' => 'orphan']);

            $this->fail('an orphan was inserted');
        } catch (ServiceException $exception) {
            $this->assertSame('data-not-found', $exception->getMessage());
            $this->assertSame($before, Trinket::query()->count());
        }
    }

    public function test_a_parent_hidden_by_its_own_scope_is_not_found(): void {
        [$relic, $widget] = $this->tree('mine');

        $relic->delete();

        $this->expectNotFound();

        (new GetService(Widget::class))
            ->columns(['title'])
            ->params(['relic_id' => $relic->id])
            ->get($widget->id);
    }

    public function test_a_wrong_grandparent_in_the_route_is_not_found(): void {
        [, $widget, $trinket] = $this->tree('mine');
        [$other] = $this->tree('other');

        $this->expectNotFound();

        (new GetService(Trinket::class))
            ->columns(['label'])
            ->params(['relic_id' => $other->id, 'widget_id' => $widget->id])
            ->get($trinket->id);
    }

    public function test_the_matching_chain_reaches_the_row(): void {
        [$relic, $widget, $trinket] = $this->tree('mine');

        $data = (new GetService(Trinket::class))
            ->columns(['label'])
            ->params(['relic_id' => $relic->id, 'widget_id' => $widget->id])
            ->get($trinket->id);

        $this->assertSame('mine', $this->label($data));
    }

    public function test_an_ancestor_missing_from_the_route_is_not_checked(): void {
        [, $widget, $trinket] = $this->tree('mine');

        $data = (new GetService(Trinket::class))
            ->columns(['label'])
            ->params(['widget_id' => $widget->id])
            ->get($trinket->id);

        $this->assertSame('mine', $this->label($data));
    }

    public function test_a_list_checks_the_parent_before_resolving_its_optional_columns(): void {
        $this->refuses('invalid-column-expression', fn () => (new ListService(Trinket::class))->standalone(true)->columns(['label'])->optionals(['nothing.label'])->list([]));
        $this->refuses('data-not-found', fn () => (new ListService(Trinket::class))->columns(['label'])->optionals(['nothing.label'])->list([]));
    }

    public function test_an_export_resolves_its_filter_columns_before_checking_the_parent(): void {
        $this->refuses('invalid-column-expression', fn () => (new ExportService(Trinket::class))->columns(['label'])->filterColumns(['nothing.label'])->export([]));
    }

    public function test_an_export_checks_the_parent_before_planning_its_query(): void {
        $this->refuses('invalid-column-expression', fn () => (new ExportService(Trinket::class))->standalone(true)->columns(['label', 'trinkets.label'])->export([]));
        $this->refuses('data-not-found', fn () => (new ExportService(Trinket::class))->columns(['label', 'trinkets.label'])->export([]));
    }

    public function test_an_import_checks_the_parent_only_once_a_row_is_written(): void {
        $this->refuses('validation-failed', fn () => (new ImportService(Trinket::class))->columns(['label'])->import(['rows' => 'x']));
        $this->refuses('import-column-unsupported', fn () => (new ImportService(Trinket::class))->columns(['label', '*amount:hidden'])->import(['rows' => [['row' => 2, 'values' => ['label' => 'a']]]]));
        $this->refuses('data-not-found', fn () => (new ImportService(Trinket::class))->columns(['label'])->import(['rows' => [['row' => 2, 'values' => ['label' => 'a']]]]));
    }

    public function test_the_identifiers_are_checked_before_the_parent(): void {
        $this->refuses('validation-failed', fn () => (new DeleteService(Trinket::class))->delete(['id' => [[1]]]));
        $this->refuses('invalid-sort-order', fn () => (new SortService(Trinket::class))->sort(['order' => [[1]]]));
        $this->refuses('invalid-arrange-order', fn () => (new ArrangeService(Trinket::class))->save(['enabled' => [[1]]]));
    }

    public function test_a_standalone_service_ignores_the_route_parent(): void {
        [, , $trinket] = $this->tree('mine');

        $data = (new GetService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->params(['widget_id' => 999999])
            ->get($trinket->id);

        $this->assertSame('mine', $this->label($data));
    }

}
