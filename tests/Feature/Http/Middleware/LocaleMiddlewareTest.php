<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Routing\Router;
use Tests\FeatureTestCase;

class LocaleMiddlewareTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware('locale-api')->post('locale-probe', fn () => ['locale' => app()->getLocale(), 'message' => i18n('errors.data-not-found')]);
    }

    public function test_a_known_locale_header_switches_the_translations(): void {
        $response = $this->postJson('locale-probe', [], ['Matrix-Locale' => 'tw']);

        $response->assertJson(['locale' => 'tw', 'message' => '查無資料']);
    }

    public function test_no_header_falls_back_to_the_application_locale(): void {
        $response = $this->postJson('locale-probe');

        $response->assertJson(['locale' => 'en', 'message' => 'Data not found']);
    }

    public function test_an_unlisted_locale_falls_back_to_the_application_locale(): void {
        $response = $this->postJson('locale-probe', [], ['Matrix-Locale' => 'jp']);

        $response->assertJson(['locale' => 'en']);
    }

}
