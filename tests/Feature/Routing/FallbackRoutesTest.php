<?php //>

namespace Tests\Feature\Routing;

use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class FallbackRoutesTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->get('host-page', fn (): string => 'from the host');
        $router->post('admin/host-endpoint', fn (): string => 'from the host');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownEndpoints(): array {
        return [
            'an admin endpoint' => ['admin/nope'],
            'a frontend endpoint' => ['api/nope'],
            'a deep admin path' => ['admin/resource/i18n/nope/get']
        ];
    }

    #[DataProvider('unknownEndpoints')]
    public function test_an_unknown_endpoint_answers_the_envelope(string $uri): void {
        $this->postJson($uri)
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
    }

    public function test_a_wrong_method_on_an_existing_endpoint_answers_the_envelope(): void {
        $this->getJson('admin/i18n/get')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 404, 'error' => 'endpoint-not-found']);
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
