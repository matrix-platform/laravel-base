<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\GadgetController;
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

class StagedSublistTest extends FeatureTestCase {

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
                ActionRoutes::mount('gadget', GadgetController::class);
            });
    }

    protected function setUp(): void {
        parent::setUp();

        $this->useVariants(['stub-gadget' => ['driver' => TestMenuTypeResolver::class, 'header' => TestHeaderFields::class, 'social' => TestSocialFields::class]]);

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
        app(MetadataRegistry::class)->register(StagedGadget::class, new StubDeclaration(new Metadata('gadget', 'title', 'widget'), [
            'title' => Definition::text(),
            'attachments' => Definition::composite('stub-gadget')
        ]));

        StagingWidgetController::$declared = ['trinkets' => 'widget/{widget_id}/trinket', 'gadgets' => 'widget/{widget_id}/gadget'];
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

    private function driveFile(): DriveNode {
        $node = new DriveNode();

        $node->parent_id = DriveNode::ROOT;
        $node->type = DriveNodeType::File;
        $node->name = 'cover.jpg';
        $node->hash = 'hash-cover';
        $node->path = date('Ym') . '/' . str()->random(32);
        $node->size = 100;
        $node->mime_type = 'image/jpeg';
        $node->width = 400;
        $node->height = 300;

        $node->save();

        return $node;
    }

    private function gadget(Widget $widget, string $title): StagedGadget {
        return StagedGadget::forceCreate(['title' => $title, 'widget_id' => $widget->id, 'attachments' => ['caption' => ['tw' => 'tw caption', 'en' => 'en caption'], 'image' => null]]);
    }

    /**
     * @param array<string, array<string, bool>> $permissions
     */
    private function regular(array $permissions, int $id = 2000): string {
        return UserFactory::new()->createOne(['id' => $id, 'permissions' => $permissions])->createToken();
    }

    private function trinket(Widget $widget, string $label, int $ranking): Trinket {
        return Trinket::forceCreate(['label' => $label, 'widget_id' => $widget->id, 'amount' => $ranking * 10, 'ranking' => $ranking]);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_the_new_page_returns_the_insert_form_without_rows(): void {
        $response = $this->admin('admin/widget/staged/trinkets');

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.title', 'Trinkets');
        $response->assertJsonPath('data.sortable', true);
        $response->assertJsonPath('data.rows', []);
        $response->assertJsonMissingPath('data.list');
        $this->assertSame(['label', 'amount', 'ranking'], array_column($response->json('data.columns'), 'name'));
    }

    public function test_the_edit_page_returns_every_existing_row_in_form_shape_and_ranking_order(): void {
        $widget = $this->widget('Alpha');
        $second = $this->trinket($widget, 'second', 2);
        $first = $this->trinket($widget, 'first', 1);

        $this->trinket($this->widget('Beta'), 'foreign', 0);

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets");

        $response->assertJsonPath('success', true);
        $this->assertSame([$first->id, $second->id], array_column($response->json('data.rows'), 'id'));
        $this->assertEquals(['id' => $first->id, 'label' => 'first', 'amount' => 10, 'ranking' => 1, 'widget_id' => $widget->id, 'widget_title' => 'Alpha'], $response->json('data.rows.0'));
    }

    public function test_the_edit_page_returns_the_list_columns_and_sorting_of_the_child(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets");

        $response->assertJsonPath('success', true);
        $this->assertSame(['columns', 'sorting'], array_keys($response->json('data.list')));
        $this->assertSame(['label', 'widget_title', 'amount', 'ranking'], array_column($response->json('data.list.columns'), 'name'));
        $this->assertSame([true, true, true, false], array_column($response->json('data.list.columns'), 'default'));
        $response->assertJsonPath('data.list.sorting', $this->admin("admin/widget/{$widget->id}/trinket")->json('data.sorting'));
    }

    public function test_the_edit_page_flattens_composite_values_of_existing_rows(): void {
        $widget = $this->widget('Alpha');

        $this->gadget($widget, 'Banner');

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets");

        $response->assertJsonPath('data.rows.0.title', 'Banner');
        $response->assertJsonPath('data.rows.0.attachments__caption__en', 'en caption');
        $response->assertJsonPath('data.rows.0.attachments__image', null);
        $response->assertJsonPath('data.sortable', false);
    }

    public function test_the_form_expands_the_variant_chosen_by_the_submitted_data(): void {
        $widget = $this->widget('Alpha');

        $header = $this->admin("admin/widget/{$widget->id}/staged/gadgets");
        $social = $this->admin("admin/widget/{$widget->id}/staged/gadgets", ['data' => ['parent_id' => 1]]);
        $fresh = $this->admin('admin/widget/staged/gadgets', ['data' => ['parent_id' => 1]]);

        $this->assertSame(['title', 'attachments__caption', 'attachments__image'], array_column($header->json('data.columns'), 'name'));
        $this->assertSame(['title', 'attachments__platform', 'attachments__url'], array_column($social->json('data.columns'), 'name'));
        $this->assertSame(['title', 'attachments__platform', 'attachments__url'], array_column($fresh->json('data.columns'), 'name'));
    }

    public function test_the_form_of_an_existing_row_uses_that_rows_update_columns(): void {
        $widget = $this->widget('Alpha');
        $gadget = $this->gadget($widget, 'Banner');

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets", ['id' => $gadget->id]);

        $this->assertSame(['title', 'attachments__caption', 'attachments__image'], array_column($response->json('data.columns'), 'name'));
    }

    public function test_validating_a_new_row_passes_without_writing_anything(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['data' => ['label' => 'fresh', 'amount' => '5']]);

        $response->assertJsonPath('success', true);
        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_validating_an_invalid_row_is_a_validation_error(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['data' => ['label' => null, 'amount' => null]]);

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertArrayHasKey('label', $response->json('fields'));
    }

    public function test_validating_runs_the_child_guards(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets/validate", ['data' => ['title' => 'refused', 'attachments__caption__tw' => null, 'attachments__caption__en' => null, 'attachments__image' => null]]);

        $response->assertJson(['success' => false, 'code' => 422, 'fields' => ['title' => ['refused']]]);
    }

    public function test_validating_attaches_the_parent_and_rolls_back_drive_files(): void {
        $widget = $this->widget('Alpha');
        $node = $this->driveFile();

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets/validate", [
            'parent' => ['title' => 'Edited'],
            'data' => ['title' => 'Banner', 'attachments__caption__tw' => null, 'attachments__caption__en' => null, 'attachments__image' => [['id' => $node->id]]]
        ]);

        $response->assertJsonPath('success', true);
        $this->assertSame(0, File::query()->count());
        $this->assertSame(0, StagedGadget::query()->count());
        $this->assertSame($widget->id, StagedGadgetController::$inspected[0]['widget_id']);
        $this->assertSame(['title' => 'Edited'], StagedGadgetController::$inspected[0]['parent']);
        $this->assertSame($widget->id, StagedGadgetController::$inspected[0]['staged']?->getKey());
    }

    public function test_validating_on_the_new_page_leaves_the_foreign_key_null(): void {
        $response = $this->admin('admin/widget/staged/gadgets/validate', [
            'parent' => ['title' => 'Draft'],
            'data' => ['title' => 'Banner', 'parent_id' => 1, 'attachments__platform' => 'x', 'attachments__url' => 'https://example.com']
        ]);

        $response->assertJsonPath('success', true);
        $this->assertNull(StagedGadgetController::$inspected[0]['widget_id']);
        $this->assertSame(['title' => 'Draft'], StagedGadgetController::$inspected[0]['parent']);
        $this->assertNull(StagedGadgetController::$inspected[0]['staged']);
    }

    public function test_validating_on_the_new_page_checks_the_variant_rules(): void {
        $response = $this->admin('admin/widget/staged/gadgets/validate', ['data' => ['title' => 'Banner', 'parent_id' => 1, 'attachments__platform' => null, 'attachments__url' => null]]);

        $response->assertJsonPath('code', 422);
        $this->assertArrayHasKey('attachments__platform', $response->json('fields'));
    }

    public function test_validating_an_existing_row_hands_its_previous_state_to_the_guards_without_saving(): void {
        $widget = $this->widget('Alpha');
        $gadget = $this->gadget($widget, 'Banner');

        $response = $this->admin("admin/widget/{$widget->id}/staged/gadgets/validate", [
            'id' => $gadget->id,
            'data' => ['title' => 'Renamed', 'attachments__caption__tw' => null, 'attachments__caption__en' => null, 'attachments__image' => null]
        ]);

        $response->assertJsonPath('success', true);

        $context = StagedGadgetController::$inspected[0]['context'];

        $this->assertIsArray($context);
        $this->assertSame('Banner', $context['title']);
        $this->assertSame('Banner', $gadget->fresh()?->title);
    }

    public function test_validating_a_row_of_another_parent_is_not_found(): void {
        $widget = $this->widget('Alpha');
        $foreign = $this->trinket($this->widget('Beta'), 'foreign', 1);

        $response = $this->admin("admin/widget/{$widget->id}/staged/trinkets/validate", ['id' => $foreign->id, 'data' => ['label' => 'x', 'amount' => '1']]);

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_route_without_a_menu_node_is_refused(): void {
        $this->useMenuFixtures('authority');

        $response = $this->admin('admin/widget/staged/trinkets');

        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_validating_a_new_row_requires_the_child_insert_permission(): void {
        $widget = $this->widget('Alpha');
        $input = ['data' => ['label' => 'fresh', 'amount' => '5']];
        $uri = "admin/widget/{$widget->id}/staged/trinkets/validate";

        $this->admin($uri, $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['update' => true]]))
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->admin($uri, $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['insert' => true]], 2001))
            ->assertJsonPath('success', true);
    }

    public function test_validating_an_existing_row_requires_the_child_update_permission(): void {
        $widget = $this->widget('Alpha');
        $trinket = $this->trinket($widget, 'first', 1);
        $input = ['id' => $trinket->id, 'data' => ['label' => 'renamed', 'amount' => '5']];
        $uri = "admin/widget/{$widget->id}/staged/trinkets/validate";

        $this->admin($uri, $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['insert' => true]]))
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->admin($uri, $input, $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['update' => true]], 2001))
            ->assertJsonPath('success', true);
    }

    public function test_the_edit_form_needs_a_child_write_permission_but_not_query(): void {
        $widget = $this->widget('Alpha');
        $uri = "admin/widget/{$widget->id}/staged/trinkets";

        $this->admin($uri, [], $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['query' => true]]))
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);

        $this->admin($uri, [], $this->regular(['widget' => ['update' => true], 'widget/{widget_id}/trinket' => ['delete' => true]], 2001))
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.list.columns.0.name', 'label');
    }

    public function test_the_new_form_needs_the_child_insert_permission(): void {
        $this->admin('admin/widget/staged/trinkets', [], $this->regular(['widget' => ['insert' => true], 'widget/{widget_id}/trinket' => ['update' => true]]))
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_a_parent_outside_the_scope_is_not_found(): void {
        $hidden = $this->widget('hidden');

        $this->admin("admin/widget/{$hidden->id}/staged/trinkets")->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->admin("admin/widget/{$hidden->id}/staged/trinkets/validate", ['data' => ['label' => 'x', 'amount' => '1']])->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->admin('admin/widget/999/staged/trinkets')->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_an_undeclared_key_is_not_found(): void {
        $this->admin('admin/widget/staged/unknown')->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_standalone_child_is_rejected(): void {
        StagingWidgetController::$declared = ['gadgets' => 'gadget'];

        $this->admin('admin/widget/staged/gadgets')->assertJson(['success' => false, 'error' => 'invalid-staged-child']);
    }

    public function test_an_unmounted_child_is_rejected(): void {
        StagingWidgetController::$declared = ['things' => 'widget/{widget_id}/thing'];

        $this->admin('admin/widget/staged/things')->assertJson(['success' => false, 'error' => 'invalid-staged-child']);
    }

    public function test_a_key_matching_a_parent_field_is_rejected(): void {
        StagingWidgetController::$declared = ['secret' => 'widget/{widget_id}/trinket'];

        $this->admin('admin/widget/staged/secret')->assertJson(['success' => false, 'error' => 'staged-key-conflict']);
    }

}
