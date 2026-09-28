<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\NewService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\Relic;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

/**
 * Nested (non-standalone) services: a child is only reachable through its own parent, the parent
 * must be visible through its own model (global scopes, soft deletes), and every ancestor named
 * in the route must match the chain the parent actually belongs to.
 */
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
