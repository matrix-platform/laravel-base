<?php //>

namespace Tests\Feature\Controllers;

use MatrixPlatform\Http\Controllers\Admin\I18nController;
use Tests\FeatureTestCase;

class I18nControllerTest extends FeatureTestCase {

    protected function defineEnvironment($app) {
        parent::defineEnvironment($app);
        $app['config']->set('app.locale', 'tw');
    }

    protected function defineRoutes($router) {
        $router->post('/i18n/{name}', [I18nController::class, 'get']);
    }

    public function test_existing_bundle_returns_data() {
        $response = $this->post('/i18n/actions');
        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_nonexistent_bundle_returns_null_data() {
        $response = $this->post('/i18n/nonexistent');
        $this->assertTrue($response->json('success'));
        $this->assertNull($response->json('data'));
    }

}
