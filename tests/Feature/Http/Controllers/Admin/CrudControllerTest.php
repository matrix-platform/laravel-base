<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\TrinketController;
use Tests\Stubs\Widget;
use Tests\Stubs\WidgetController;

class CrudControllerTest extends FeatureTestCase {

    private string $token;

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(function (): void {
                Route::prefix('widget')->group(fn () => ActionRoutes::scan(WidgetController::class));
                Route::prefix('widget/{widget_id}/trinket')->group(fn () => ActionRoutes::scan(TrinketController::class));
            });
    }

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('crud');

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function admin(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    private function widget(string $title): Widget {
        return Widget::forceCreate(['title' => $title]);
    }

    public function test_the_list_route_is_mounted_on_the_bare_prefix(): void {
        $this->admin('admin/widget')->assertJsonPath('success', true);
    }

    public function test_the_list_response_carries_every_section(): void {
        $widget = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'a1', 'widget_id' => $widget->id]);

        $response = $this->admin('admin/widget');

        $response->assertJsonPath('data.title', 'Widgets');
        $response->assertJsonPath('data.subtitle', null);
        $response->assertJsonPath('data.breadcrumbs', [['label' => null, 'path' => 'widget', 'title' => 'Widgets']]);
        $response->assertJsonPath('data.rows.0.title', 'Alpha');
        $response->assertJsonPath('data.rows.0.trinkets_count', 1);
        $response->assertJsonPath('data.rows.0.actions', ['edit', 'delete']);
        $response->assertJsonPath('data.pagination', ['page' => 1, 'size' => 10, 'total' => 1]);
        $response->assertJsonPath('data.columns.0.name', 'id');
        $response->assertJsonPath('data.columns.0.presentation', 'hidden');
        $response->assertJsonPath('data.actions.page.0.type', 'new');
        $response->assertJsonPath('data.actions.row.0.type', 'edit');
    }

    public function test_the_title_falls_back_to_null_without_a_reachable_menu(): void {
        $token = UserFactory::new()->createOne(['id' => 20000])->createToken();
        $response = $this->withToken($token)->postJson('admin/widget');

        $response->assertJsonPath('data.title', null);
        $response->assertJsonPath('data.breadcrumbs', []);
    }

    public function test_an_aggregate_column_carries_the_derived_menu_path(): void {
        $this->widget('Alpha');

        $response = $this->admin('admin/widget');

        $this->assertSame('widget/{id}/trinket', $response->json('data.columns.2.path'));
    }

    public function test_an_aggregate_column_has_no_path_when_the_menu_lacks_it(): void {
        $this->useMenuFixtures('authority');
        $this->widget('Alpha');

        $response = $this->admin('admin/widget');

        $this->assertNull($response->json('data.columns.2.path'));
    }

    public function test_the_default_sorting_is_applied(): void {
        $first = $this->widget('Alpha');
        $second = $this->widget('Beta');

        $first->ranking = 10;
        $second->ranking = 20;

        $first->save();
        $second->save();

        $response = $this->admin('admin/widget');

        $this->assertSame(['Beta', 'Alpha'], array_column($response->json('data.rows'), 'title'));
    }

    public function test_the_page_size_defaults_to_ten_and_zero_means_everything(): void {
        foreach (range(1, 12) as $index) {
            $this->widget("W{$index}");
        }

        $this->assertCount(10, $this->admin('admin/widget')->json('data.rows'));
        $this->assertCount(12, $this->admin('admin/widget', ['size' => 0])->json('data.rows'));
        $this->assertCount(12, $this->admin('admin/widget', ['size' => 'abc'])->json('data.rows'));
    }

    public function test_the_get_response_carries_the_row_and_its_columns(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$widget->id}");

        $response->assertJsonPath('data.title', 'Widget');
        $response->assertJsonPath('data.subtitle', 'Alpha');
        $response->assertJsonPath('data.data.title', 'Alpha');
        $response->assertJsonPath('data.actions.0.type', 'update');
        $response->assertJsonPath('data.actions.0.url', 'widget/{id}/update');
    }

    public function test_the_new_response_fills_every_column_with_null(): void {
        $response = $this->admin('admin/widget/new');

        $response->assertJsonPath('data.title', 'New Widget');
        $response->assertJsonPath('data.data.title', null);
        $response->assertJsonPath('data.data.secret', null);
        $response->assertJsonPath('data.actions.0.type', 'insert');
    }

    public function test_insert_creates_the_row_and_writes_an_audit_entry(): void {
        $response = $this->admin('admin/widget/insert', ['title' => 'Created', 'secret' => null, 'enable_time' => null]);

        $id = $response->json('data.id');

        $this->assertNotNull($id);
        $this->assertSame('Created', Widget::query()->findOrFail(intval($id))->title);
        $this->assertSame(1, ManipulationLog::query()->where('data_type', 'stub_widget')->count());
    }

    public function test_a_missing_key_is_a_validation_error(): void {
        $response = $this->admin('admin/widget/insert', ['title' => 'Created']);

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
        $this->assertArrayHasKey('secret', $response->json('fields'));
    }

    public function test_a_required_column_rejects_null(): void {
        $response = $this->admin('admin/widget/insert', ['title' => null, 'secret' => null, 'enable_time' => null]);

        $response->assertJsonPath('code', 422);
    }

    public function test_nothing_is_written_when_the_action_fails(): void {
        $this->admin('admin/widget/insert', ['title' => null, 'secret' => null, 'enable_time' => null]);

        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(0, ManipulationLog::query()->where('data_type', 'stub_widget')->count());
    }

    public function test_update_writes_only_the_declared_columns(): void {
        $widget = $this->widget('Alpha');

        $this->admin("admin/widget/{$widget->id}/update", ['title' => 'Renamed', 'secret' => 'hidden', 'enable_time' => null]);

        $widget->refresh();

        $this->assertSame('Renamed', $widget->title);
        $this->assertSame('hidden', $widget->secret);
    }

    public function test_delete_removes_the_rows_and_reports_the_identifiers(): void {
        $first = $this->widget('Alpha');
        $second = $this->widget('Beta');

        $response = $this->admin('admin/widget/delete', ['id' => [$first->id, $second->id, $first->id]]);

        $this->assertSame([$first->id, $second->id], $response->json('data.id'));
        $this->assertSame(0, Widget::query()->count());
    }

    public function test_delete_refuses_when_an_identifier_is_unknown(): void {
        $widget = $this->widget('Alpha');

        $response = $this->admin('admin/widget/delete', ['id' => [$widget->id, 999999]]);

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->assertSame(1, Widget::query()->count());
    }

    public function test_delete_accepts_an_empty_selection(): void {
        $response = $this->admin('admin/widget/delete', ['id' => []]);

        $this->assertSame([], $response->json('data.id'));
    }

    public function test_a_nested_list_is_scoped_to_its_parent(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $beta->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket");

        $this->assertSame(['mine'], array_column($response->json('data.rows'), 'label'));
        $response->assertJsonPath('data.subtitle', 'Alpha');
        $response->assertJsonPath('data.context.widget_id', strval($alpha->id));
    }

    public function test_a_nested_breadcrumb_renders_the_parent_path(): void {
        $alpha = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$alpha->id}/trinket");

        $this->assertSame([
            ['label' => null, 'path' => 'widget', 'title' => 'Widgets'],
            ['label' => null, 'path' => "widget/{$alpha->id}", 'title' => 'Widget'],
            ['label' => 'Alpha', 'path' => "widget/{$alpha->id}/trinket", 'title' => 'Trinkets']
        ], $response->json('data.breadcrumbs'));
    }

    public function test_a_nested_get_reads_its_own_record(): void {
        $alpha = $this->widget('Alpha');
        $trinket = Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}");

        $response->assertJsonPath('data.subtitle', 'mine');
        $response->assertJsonPath('data.data.label', 'mine');
    }

    public function test_a_nested_update_writes_its_own_record(): void {
        $alpha = $this->widget('Alpha');
        $trinket = Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);

        $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}/update", ['label' => 'renamed', 'amount' => null]);

        $this->assertSame('renamed', $trinket->refresh()->label);
    }

    public function test_a_nested_get_cannot_reach_another_parent(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        $trinket = Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $beta->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}");

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_nested_update_cannot_reach_another_parent(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        $trinket = Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $beta->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}/update", ['label' => 'stolen', 'amount' => null]);

        $response->assertJsonPath('code', 404);
        $this->assertSame('theirs', $trinket->refresh()->label);
    }

    public function test_a_nested_insert_takes_the_parent_from_the_route(): void {
        $alpha = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/insert", ['label' => 'mine', 'amount' => null, 'widget_id' => 999999]);

        $this->assertSame($alpha->id, Trinket::query()->findOrFail(intval($response->json('data.id')))->widget_id);
    }

    public function test_a_readonly_column_is_not_writable_on_update(): void {
        $alpha = $this->widget('Alpha');
        $trinket = Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);
        $ranking = $trinket->refresh()->ranking;

        $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}/update", ['label' => 'mine', 'amount' => null, 'ranking' => 9999]);

        $this->assertSame($ranking, $trinket->refresh()->ranking);
    }

    public function test_a_readonly_column_is_not_required_on_insert(): void {
        $alpha = $this->widget('Alpha');

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/insert", ['label' => 'mine', 'amount' => null]);

        $response->assertJsonPath('success', true);
    }

    public function test_a_joined_column_is_shown_but_never_written_back(): void {
        $alpha = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket");

        $this->assertSame('Alpha', $response->json('data.rows.0.widget_title'));
    }

    public function test_a_row_action_is_hidden_when_its_condition_fails(): void {
        $alpha = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'locked', 'widget_id' => $alpha->id]);
        Trinket::forceCreate(['label' => 'open', 'widget_id' => $alpha->id]);

        $rows = $this->admin("admin/widget/{$alpha->id}/trinket")->json('data.rows');
        $actions = array_combine(array_column($rows, 'label'), array_column($rows, 'actions'));

        $this->assertSame(['edit', 'copy'], $actions['locked']);
        $this->assertSame(['edit', 'copy', 'delete'], $actions['open']);
    }

    public function test_a_guard_refuses_the_whole_delete_before_anything_is_removed(): void {
        $alpha = $this->widget('Alpha');

        $locked = Trinket::forceCreate(['label' => 'locked', 'widget_id' => $alpha->id]);
        $open = Trinket::forceCreate(['label' => 'open', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/delete", ['id' => [$open->id, $locked->id]]);

        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
        $this->assertSame(2, Trinket::query()->count());
    }

    public function test_copy_duplicates_the_row_and_its_declared_children(): void {
        $alpha = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'a', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/copy");
        $copy = intval($response->json('data.id'));

        $response->assertJsonPath('success', true);
        $this->assertNotSame($alpha->id, $copy);
        $this->assertSame('Alpha', Widget::query()->findOrFail($copy)->title);
        $this->assertSame(1, Trinket::query()->where('widget_id', $copy)->count());
    }

    public function test_a_nested_copy_duplicates_its_own_record(): void {
        $alpha = $this->widget('Alpha');
        $trinket = Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}/copy");
        $copy = Trinket::query()->findOrFail(intval($response->json('data.id')));

        $this->assertNotSame($trinket->id, $copy->id);
        $this->assertSame('mine', $copy->label);
        $this->assertSame($alpha->id, $copy->widget_id);
    }

    public function test_a_nested_copy_cannot_reach_another_parent(): void {
        $alpha = $this->widget('Alpha');
        $beta = $this->widget('Beta');

        $trinket = Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $beta->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/trinket/{$trinket->id}/copy");

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
        $this->assertSame(1, Trinket::query()->count());
    }

    public function test_a_guard_refusing_a_child_rolls_back_the_whole_copy(): void {
        $alpha = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'locked', 'widget_id' => $alpha->id]);

        $response = $this->admin("admin/widget/{$alpha->id}/copy");

        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
        $this->assertSame(1, Widget::query()->count());
        $this->assertSame(1, Trinket::query()->count());
    }

    public function test_the_copy_action_carries_its_translated_confirmation_and_url(): void {
        $alpha = $this->widget('Alpha');

        Trinket::forceCreate(['label' => 'mine', 'widget_id' => $alpha->id]);

        $actions = $this->admin("admin/widget/{$alpha->id}/trinket")->json('data.actions.row');
        $copy = array_values(array_filter($actions, fn (array $action): bool => $action['type'] === 'copy'))[0];

        $this->assertSame('Copy', $copy['title']);
        $this->assertSame('Are you sure you want to copy?', $copy['confirm']);
        $this->assertSame('widget/{widget_id}/trinket/{id}/copy', $copy['url']);
    }

}
