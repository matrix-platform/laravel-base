<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use MatrixPlatform\Models\User;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\FilteredWidgetController;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class FilterHandlersTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(fn () => Route::prefix('filtered-widget')->group(fn () => ActionRoutes::scan(FilteredWidgetController::class)));
    }

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        Widget::forceCreate(['title' => 'Alpha']);
        Widget::forceCreate(['title' => 'Beta']);
    }

    public function test_declared_filters_apply_to_the_list(): void {
        $token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();

        $rows = $this->withToken($token)
            ->postJson('admin/filtered-widget', ['filters' => ['note' => ['op' => 'contains', 'value' => 'Beta']]])
            ->json('data.rows');

        $this->assertSame(['Beta'], array_column($rows, 'title'));
    }

    public function test_declared_filters_apply_to_the_export(): void {
        $token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();

        $rows = $this->withToken($token)
            ->postJson('admin/filtered-widget/export', ['filters' => ['note' => ['op' => 'contains', 'value' => 'Beta']]])
            ->json('data.rows');

        $this->assertSame(['Beta'], array_column($rows, 'title'));
    }

}
