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
use Tests\Stubs\Widget;
use Tests\Stubs\WritingTrinketController;

class StagedRowWritersTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api', 'permission-api'])
            ->prefix('admin')
            ->group(function (): void {
                ActionRoutes::mount('widget', StagingWidgetController::class);
                ActionRoutes::mount('widget/{widget_id}/trinket', WritingTrinketController::class);
                ActionRoutes::mount('widget/{widget_id}/gadget', StagedGadgetController::class);
            });
    }

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('staged');
        $this->useVariants(['stub-gadget' => ['driver' => TestMenuTypeResolver::class, 'header' => TestHeaderFields::class, 'social' => TestSocialFields::class]]);

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
        app(MetadataRegistry::class)->register(StagedGadget::class, new StubDeclaration(new Metadata('gadget', 'title', 'widget'), [
            'title' => Definition::text(),
            'attachments' => Definition::composite('stub-gadget')
        ]));

        StagingWidgetController::$declared = ['trinkets' => 'widget/{widget_id}/trinket', 'gadgets' => 'widget/{widget_id}/gadget'];
        StagingWidgetController::$hooks = [];
        StagingWidgetController::$refused = null;
        StagedGadgetController::$inspected = [];
        WritingTrinketController::$calls = [];

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
     * @param array<string, mixed> $staged
     * @return array<string, mixed>
     */
    private function parent(string $title, array $staged): array {
        return ['title' => $title, 'secret' => null, 'enable_time' => null, ...$staged];
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function regular(array $permissions, int $id = 2000): string {
        return UserFactory::new()->createOne(['id' => $id, 'permissions' => $permissions])->createToken();
    }

    private function trinket(Widget $widget, string $label): Trinket {
        return Trinket::forceCreate(['label' => $label, 'widget_id' => $widget->id, 'amount' => 1, 'ranking' => 0]);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_saving_routes_every_write_through_the_row_writers(): void {
        $widget = $this->widget('Alpha');
        $renamed = $this->trinket($widget, 'old');
        $removed = $this->trinket($widget, 'removed');

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Alpha', [
            'trinkets' => ['rows' => [['id' => $renamed->id, 'label' => 'new', 'amount' => '9'], ['label' => 'added', 'amount' => '9']], 'delete' => [$removed->id]]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertSame([['delete', strval($removed->id)], ['update', strval($renamed->id)], ['insert']], WritingTrinketController::$calls);
        $this->assertNull($removed->fresh());
        $this->assertSame(300, $renamed->fresh()?->amount);
        $this->assertSame(500, Trinket::query()->where('label', 'added')->value('amount'));
    }

    public function test_the_new_page_inserts_through_the_row_writer_under_the_saved_parent(): void {
        $response = $this->admin('admin/widget/insert', $this->parent('Alpha', ['trinkets' => ['rows' => [['label' => 'one']]]]));

        $response->assertJsonPath('success', true);
        $this->assertSame([['insert']], WritingTrinketController::$calls);
        $this->assertSame(intval($response->json('data.id')), Trinket::query()->where('label', 'one')->value('widget_id'));
    }

    public function test_validating_a_new_row_writes_it_returns_the_computed_row_and_rolls_back(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['data' => ['label' => 'fresh', 'amount' => '1']]);

        $response->assertJsonPath('success', true);
        $this->assertSame([['insert']], WritingTrinketController::$calls);
        $this->assertEqualsCanonicalizing(['id', 'label', 'amount', 'ranking', 'widget_id', 'widget_title', 'badge'], array_keys($response->json('data')));
        $response->assertJsonPath('data.label', 'fresh');
        $response->assertJsonPath('data.widget_title', 'Alpha');
        $response->assertJsonPath('data.badge', '#' . $response->json('data.id'));
        $response->assertJsonPath('data.amount', 500);
        $response->assertJsonPath('data.widget_id', $widget->id);
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_validating_an_existing_row_returns_the_computed_row_without_saving(): void {
        $widget = $this->widget('Alpha');
        $trinket = $this->trinket($widget, 'old');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['id' => $trinket->id, 'data' => ['label' => 'renamed']]);

        $response->assertJsonPath('success', true);
        $this->assertSame([['update', strval($trinket->id)]], WritingTrinketController::$calls);
        $response->assertJsonPath('data.id', $trinket->id);
        $response->assertJsonPath('data.amount', 700);
        $response->assertJsonPath('data.label', 'renamed');
        $response->assertJsonPath('data.badge', "#{$trinket->id}");
        $fresh = $trinket->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('old', $fresh->label);
        $this->assertSame(1, $fresh->amount);
    }

    public function test_the_edit_page_merges_the_list_values_of_each_row_with_the_form_values_winning(): void {
        $widget = $this->widget('Alpha');
        $trinket = $this->trinket($widget, 'old');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets");

        $response->assertJsonPath('success', true);
        $this->assertEquals(['id' => $trinket->id, 'label' => 'old', 'amount' => 1, 'ranking' => 0, 'widget_id' => $widget->id, 'widget_title' => 'Alpha', 'badge' => "#{$trinket->id}"], $response->json('data.rows.0'));
        $response->assertJsonPath('data.list.columns.1.name', 'widget_title');
    }

    public function test_a_service_error_carrying_fields_is_reported_on_the_row(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Renamed', [
            'trinkets' => ['rows' => [['label' => 'fine'], ['label' => 'sold out']]]
        ]));

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertSame(['trinkets.1.label' => ['out-of-stock']], $response->json('fields'));
        $this->assertSame('Alpha', $widget->fresh()?->title);
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_validating_turns_a_service_error_carrying_fields_into_a_validation_error(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['data' => ['label' => 'sold out']]);

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertSame(['label' => ['out-of-stock']], $response->json('fields'));
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_abilities_come_from_the_row_writer_only_when_a_parent_exists(): void {
        $locked = $this->widget('Locked');
        $open = $this->widget('Open');
        $all = ['insert' => true, 'update' => true, 'delete' => true];

        $this->admin("admin/widget/{$locked->id}/staged/trinkets")->assertJsonPath('data.abilities', ['insert' => true, 'update' => false, 'delete' => false]);
        $this->admin("admin/widget/{$open->id}/staged/trinkets")->assertJsonPath('data.abilities', ['insert' => true, 'update' => false, 'delete' => true]);
        $this->admin('admin/widget/staged/trinkets')->assertJsonPath('data.abilities', $all);
        $this->admin("admin/widget/{$locked->id}/staged/gadgets")->assertJsonPath('data.abilities', $all);
    }

    public function test_abilities_do_not_block_saving(): void {
        $widget = $this->widget('Locked');
        $trinket = $this->trinket($widget, 'old');

        $response = $this->admin("admin/widget/{$widget->id}/update", $this->parent('Locked', [
            'trinkets' => ['rows' => [['id' => $trinket->id, 'label' => 'new']], 'delete' => []]
        ]));

        $response->assertJsonPath('success', true);
        $this->assertSame('new', $trinket->fresh()?->label);
    }

    public function test_the_permission_tag_of_each_operation_comes_from_the_row_writer(): void {
        $widget = $this->widget('Alpha');
        $trinket = $this->trinket($widget, 'old');
        $input = $this->parent('Alpha', ['trinkets' => ['delete' => [$trinket->id]]]);

        $this->admin("admin/widget/{$widget->id}/update", $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['delete' => true]]))
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->assertNotNull($trinket->fresh());

        $this->admin("admin/widget/{$widget->id}/update", $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['update' => true]], 2001))
            ->assertJsonPath('success', true);

        $this->assertNull($trinket->fresh());
    }

    public function test_a_child_without_row_writers_still_validates_to_an_empty_result(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets/validate", ['data' => ['title' => 'Banner', 'attachments__caption__tw' => null, 'attachments__caption__en' => null, 'attachments__image' => null]]);

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data', []);
        $this->assertSame(0, StagedGadget::query()->count());
    }

}
