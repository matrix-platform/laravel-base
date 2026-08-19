<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Tests\FeatureTestCase;

class FallbackRoutesTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->get('host-page', fn (): string => 'from the host');
        $router->post('admin/host-endpoint', fn (): string => 'from the host');
    }

    public function test_an_unknown_admin_endpoint_answers_the_envelope(): void {
        $this->postJson('admin/nope')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    public function test_an_unknown_frontend_endpoint_answers_the_envelope(): void {
        $this->postJson('api/nope')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    public function test_a_wrong_method_on_an_existing_endpoint_answers_the_envelope(): void {
        $this->getJson('admin/i18n/get')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    public function test_a_deep_unknown_path_answers_the_envelope(): void {
        $this->postJson('admin/resource/i18n/nope/get')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    public function test_the_registered_endpoints_still_reach_their_controllers(): void {
        $this->postJson('admin/i18n/get', ['name' => 'errors'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_a_host_route_registered_after_the_package_still_reaches_its_handler(): void {
        $this->postJson('admin/host-endpoint')
            ->assertOk()
            ->assertSee('from the host');
    }

    public function test_a_route_outside_the_package_prefixes_keeps_its_own_behaviour(): void {
        $this->get('host-page')
            ->assertOk()
            ->assertSee('from the host');
    }

    public function test_an_unknown_path_outside_the_package_prefixes_stays_a_real_404(): void {
        $this->getJson('host-nope')
            ->assertNotFound()
            ->assertJsonMissingPath('error');
    }

}
