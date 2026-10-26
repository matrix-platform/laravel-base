<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StagedGadget;
use Tests\Stubs\StagedGadgetController;
use Tests\Stubs\StagingWidgetController;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\TestHeaderFields;
use Tests\Stubs\TestMenuTypeResolver;
use Tests\Stubs\TestSocialFields;
use Tests\Stubs\Trinket;
use Tests\Stubs\TrinketController;
use Tests\Stubs\Widget;

class StagedSaveTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $this->useMenuFixtures('staged');

        $router->middleware(['envelope-api', 'user-api', 'permission-api'])
            ->prefix('admin')
            ->group(function (): void {
                ActionRoutes::mount('widget', StagingWidgetController::class);
                ActionRoutes::mount('widget/{widget_id}/trinket', TrinketController::class);
                ActionRoutes::mount('widget/{widget_id}/gadget', StagedGadgetController::class);
            });
    }

    protected function setUp(): void {
        parent::setUp();

        $this->useVariants(['stub-gadget' => ['driver' => TestMenuTypeResolver::class, 'header' => TestHeaderFields::class, 'social' => TestSocialFields::class]]);

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget'), ['label' => Definition::text(unique: true)]));
        app(MetadataRegistry::class)->register(StagedGadget::class, new StubDeclaration(new Metadata('gadget', 'title', 'widget'), [
            'title' => Definition::text(),
            'attachments' => Definition::composite('stub-gadget')
        ]));

        StagingWidgetController::$declared = ['trinkets' => 'widget/{widget_id}/trinket', 'gadgets' => 'widget/{widget_id}/gadget'];
        StagingWidgetController::$hooks = [];
        StagingWidgetController::$refused = null;
        StagedGadgetController::$inspected = [];

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function admin(string $uri, array $input = [], ?string $token = null): TestResponse {
        return $this->withToken($token === null ? $this->token : $token)->postJson($uri, $input);
    }

    /**
     * @return array<string, mixed>
     */
    private function gadgetRow(string $title): array {
        return ['title' => $title, 'attachments__caption__tw' => null, 'attachments__caption__en' => null, 'attachments__image' => null];
    }

    /**
     * @return list<string>
     */
    private function labels(Widget $widget): array {
        $labels = Trinket::query()
            ->where('widget_id', $widget->id)
            ->orderBy('ranking')
            ->pluck('label')
            ->all();

        return array_values(array_map(fn (mixed $label): string => is_string($label) ? $label : '', $labels));
    }

    /**
     * @param array<string, mixed> $staged
     * @return array<string, mixed>
     */
    private function parent(string|null $title, array $staged): array {
        return ['title' => $title, 'secret' => null, 'enable_time' => null, ...$staged];
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function regular(array $permissions): string {
        return UserFactory::new()->createOne(['id' => 2000, 'permissions' => $permissions])->createToken();
    }

    private function trinket(Widget $widget, string $label, int $ranking = 0): Trinket {
        return Trinket::forceCreate(['label' => $label, 'widget_id' => $widget->id, 'amount' => 1, 'ranking' => $ranking]);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_the_new_page_writes_the_parent_and_its_rows_together(): void {
        $response = $this->admin('admin/widget/insert', $this->parent('Alpha', [
            'trinkets' => ['rows' => [['label' => 'one', 'amount' => '1'], ['label' => 'two', 'amount' => '2']]],
            'gadgets' => ['rows' => [$this->gadgetRow('Banner')]]
        ]));

        $response->assertJsonPath('success', true);

        $widget = Widget::query()->findOrFail(intval($response->json('data.id')));

        $this->assertSame('Alpha', $widget->title);
        $this->assertEqualsCanonicalizing(['one', 'two'], $this->labels($widget));
        $this->assertSame(1, StagedGadget::query()->where('widget_id', $widget->id)->count());
    }

    public function test_the_edit_page_applies_deletes_updates_and_inserts(): void {
        $widget = $this->widget('Alpha');
        $kept = $this->trinket($widget, 'kept', 1);
        $renamed = $this->trinket($widget, 'old', 2);
        $removed = $this->trinket($widget, 'removed', 3);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', [
            'trinkets' => ['rows' => [['id' => $renamed->id, 'label' => 'new name', 'amount' => '9'], ['label' => 'added', 'amount' => '3']], 'delete' => [$removed->id]]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertSame('Beta', $widget->fresh()?->title);
        $this->assertNull($removed->fresh());
        $this->assertSame('new name', $renamed->fresh()?->label);
        $this->assertSame('kept', $kept->fresh()?->label);
        $this->assertEqualsCanonicalizing(['kept', 'new name', 'added'], $this->labels($widget));
    }

    public function test_the_order_places_new_and_untouched_rows(): void {
        $widget = $this->widget('Alpha');
        $first = $this->trinket($widget, 'first', 1);
        $second = $this->trinket($widget, 'second', 2);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', [
            'trinkets' => ['rows' => [['label' => 'added', 'amount' => '3']], 'order' => [0, "id:{$second->id}", "id:{$first->id}"]]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertSame(['added', 'second', 'first'], $this->labels($widget));
    }

    public function test_an_incomplete_order_is_reported_on_the_key(): void {
        $widget = $this->widget('Alpha');
        $first = $this->trinket($widget, 'first', 1);

        $this->trinket($widget, 'second', 2);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', ['trinkets' => ['order' => ["id:{$first->id}"]]]));

        $response->assertJson(['success' => false, 'code' => 422, 'fields' => ['trinkets' => ['invalid-sort-order']]]);
    }

    public function test_an_order_on_a_child_that_cannot_be_sorted_is_not_found(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Renamed', ['gadgets' => ['rows' => [$this->gadgetRow('Banner')], 'order' => [0]]]));

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->assertSame('Alpha', $widget->fresh()?->title);
        $this->assertSame(0, StagedGadget::query()->count());
    }

    public function test_a_refused_delete_is_reported_under_its_id(): void {
        $widget = $this->widget('Alpha');
        $locked = $this->trinket($widget, 'locked', 1);
        $referenced = $this->trinket($widget, 'referenced', 2);

        Trinket::forceCreate(['label' => 'child', 'trinket_id' => $referenced->id]);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', ['trinkets' => ['delete' => [$locked->id, $referenced->id]]]));

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertSame(["trinkets.delete.{$locked->id}" => ['permission-denied'], "trinkets.delete.{$referenced->id}" => ['data-in-use']], $response->json('fields'));
    }

    public function test_deleting_a_value_and_adding_it_again_succeeds(): void {
        $widget = $this->widget('Alpha');
        $old = $this->trinket($widget, 'same', 1);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', [
            'trinkets' => ['rows' => [['label' => 'same', 'amount' => '1']], 'delete' => [$old->id]]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertNull($old->fresh());
        $this->assertSame(['same'], $this->labels($widget));
    }

    public function test_parent_and_row_errors_come_back_together_and_nothing_is_written_on_the_edit_page(): void {
        $widget = $this->widget('Alpha');
        $kept = $this->trinket($widget, 'kept', 1);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent(null, [
            'trinkets' => ['rows' => [['label' => 'fine', 'amount' => '1'], ['label' => null, 'amount' => '2']], 'delete' => [$kept->id]]
        ]));

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertSame(['title', 'trinkets.1.label'], array_keys($response->json('fields')));
        $this->assertSame('Alpha', $widget->fresh()?->title);
        $this->assertSame(['kept'], $this->labels($widget));
    }

    public function test_parent_and_row_errors_come_back_together_on_the_new_page(): void {
        $response = $this->admin('admin/widget/insert', $this->parent(null, [
            'gadgets' => ['rows' => [$this->gadgetRow('fine'), $this->gadgetRow('refused')]]
        ]));

        $response->assertJson(['success' => false, 'code' => 422, 'fields' => ['title' => ['required'], 'gadgets.1.title' => ['refused']]]);
        $this->assertNull(StagedGadgetController::$inspected[0]['widget_id']);
        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(0, StagedGadget::query()->count());
    }

    public function test_a_row_error_rolls_back_the_parent_on_the_new_page(): void {
        $response = $this->admin('admin/widget/insert', $this->parent('Alpha', ['trinkets' => ['rows' => [['label' => null, 'amount' => '1']]]]));

        $response->assertJson(['success' => false, 'code' => 422, 'fields' => ['trinkets.0.label' => ['required']]]);
        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_a_row_of_another_parent_fails_the_whole_save(): void {
        $widget = $this->widget('Alpha');
        $foreign = $this->trinket($this->widget('Beta'), 'foreign', 1);

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Renamed', ['trinkets' => ['delete' => [$foreign->id]]]));

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->assertSame('Alpha', $widget->fresh()?->title);
        $this->assertNotNull($foreign->fresh());
    }

    public function test_the_staged_context_carries_the_parent_body_and_the_saved_parent(): void {
        $widget = $this->widget('Alpha');

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', ['gadgets' => ['rows' => [$this->gadgetRow('Banner')]]]))->assertJsonPath('success', true);

        $this->assertSame(['title' => 'Beta', 'secret' => null, 'enable_time' => null], StagedGadgetController::$inspected[0]['parent']);
        $this->assertSame('Beta', StagedGadgetController::$inspected[0]['staged']?->getAttribute('title'));
        $this->assertSame($widget->id, StagedGadgetController::$inspected[0]['widget_id']);
    }

    public function test_validate_staged_runs_after_every_key_is_written_and_after_staged_follows(): void {
        $response = $this->admin('admin/widget/insert', $this->parent('Alpha', [
            'trinkets' => ['rows' => [['label' => 'one', 'amount' => '1']]],
            'gadgets' => ['rows' => [$this->gadgetRow('Banner')]]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertSame([['validate', 'trinkets', 1, 1], ['validate', 'gadgets', 1, 1], ['after', 'trinkets'], ['after', 'gadgets']], StagingWidgetController::$hooks);
    }

    public function test_a_refusing_validate_staged_rolls_everything_back_and_skips_after_staged(): void {
        StagingWidgetController::$refused = 'gadgets';

        $response = $this->admin('admin/widget/insert', $this->parent('Alpha', [
            'trinkets' => ['rows' => [['label' => 'one', 'amount' => '1']]],
            'gadgets' => ['rows' => [$this->gadgetRow('Banner')]]
        ]));

        $response->assertJson(['success' => false, 'code' => 422, 'fields' => ['gadgets' => ['refused']]]);
        $this->assertSame(['validate', 'validate'], array_column(StagingWidgetController::$hooks, 0));
        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_a_body_without_staged_keys_saves_as_before(): void {
        $widget = $this->widget('Alpha');

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', []))->assertJsonPath('success', true);

        $this->assertSame('Beta', $widget->fresh()?->title);
        $this->assertSame([], StagingWidgetController::$hooks);
    }

    public function test_each_change_needs_its_child_permission(): void {
        $widget = $this->widget('Alpha');
        $trinket = $this->trinket($widget, 'kept', 1);
        $token = $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['insert' => true, 'update' => true]]);

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', ['trinkets' => ['delete' => [$trinket->id]]]), $token)
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->assertSame('Alpha', $widget->fresh()?->title);
        $this->assertNotNull($trinket->fresh());

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', ['trinkets' => ['rows' => [['label' => 'added', 'amount' => '1']]]]), $token)
            ->assertJsonPath('success', true);
    }

    public function test_reordering_needs_the_child_update_permission(): void {
        $widget = $this->widget('Alpha');
        $first = $this->trinket($widget, 'first', 1);
        $second = $this->trinket($widget, 'second', 2);
        $token = $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['insert' => true, 'delete' => true]]);

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', ['trinkets' => ['order' => ["id:{$second->id}", "id:{$first->id}"]]]), $token)
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->assertSame(['first', 'second'], $this->labels($widget));
    }

    public function test_inserting_rows_needs_the_child_insert_permission(): void {
        $widget = $this->widget('Alpha');
        $token = $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['update' => true, 'delete' => true]]);

        $this->admin("admin/widget/{$widget->id}/update", $this->parent('Beta', ['trinkets' => ['rows' => [['label' => 'added', 'amount' => '1']]]]), $token)
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
