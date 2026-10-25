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
use Tests\Stubs\ListingWidgetController;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class ListingOverrideTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware(['envelope-api', 'user-api'])
            ->prefix('admin')
            ->group(fn () => Route::prefix('listing-widget')->group(fn () => ActionRoutes::scan(ListingWidgetController::class)));
    }

    protected function setUp(): void {
        parent::setUp();

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        Widget::forceCreate(['title' => 'Alpha'])->forceFill(['ip' => '10.0.0.1'])->save();
        Widget::forceCreate(['title' => 'Beta'])->forceFill(['ip' => '10.0.0.2'])->save();
    }

    public function test_an_overridden_listing_shapes_the_list(): void {
        $token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();

        $rows = $this->withToken($token)
            ->postJson('admin/listing-widget', ['filters' => ['ip' => ['op' => 'eq', 'value' => '10.0.0.2']]])
            ->json('data.rows');

        $this->assertSame(['Beta'], array_column($rows, 'title'));
    }

    public function test_an_overridden_listing_also_feeds_the_export_filters(): void {
        $token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();

        $rows = $this->withToken($token)
            ->postJson('admin/listing-widget/export', ['filters' => ['ip' => ['op' => 'eq', 'value' => '10.0.0.2']]])
            ->json('data.rows');

        $this->assertSame(['Beta'], array_column($rows, 'title'));
    }

}
