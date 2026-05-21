<?php //>

namespace Tests\Feature\Middleware;

use MatrixPlatform\Http\Middleware\LocaleMiddleware;
use Tests\FeatureTestCase;

class LocaleMiddlewareTest extends FeatureTestCase {

    protected function defineEnvironment($app) {
        parent::defineEnvironment($app);
        $app['config']->set('matrix.locales', ['tw']);
    }

    protected function defineRoutes($router) {
        $router->middleware(LocaleMiddleware::class)->get('/test-locale', fn () => response()->json(['locale' => app()->getLocale()]));
    }

    public function test_whitelisted_locale_is_set() {
        $response = $this->withHeader('Matrix-Locale', 'tw')->get('/test-locale');
        $this->assertEquals('tw', $response->json('locale'));
    }

    public function test_non_whitelisted_locale_falls_back_to_default() {
        $response = $this->withHeader('Matrix-Locale', 'jp')->get('/test-locale');
        $this->assertEquals(config('app.locale'), $response->json('locale'));
    }

    public function test_missing_header_falls_back_to_default() {
        $response = $this->get('/test-locale');
        $this->assertEquals(config('app.locale'), $response->json('locale'));
    }

}
