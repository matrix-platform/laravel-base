<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Routing\Router;
use MatrixPlatform\Routing\ActionRoutes;
use Tests\FeatureTestCase;
use Tests\Stubs\StubController;

class EnvelopeMiddlewareTest extends FeatureTestCase {

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router->middleware('envelope-api')->group(fn () => ActionRoutes::scan(StubController::class));
    }

    public function test_a_service_exception_keeps_its_own_code_and_slug(): void {
        $response = $this->postJson('boom');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 409, 'error' => 'data-conflicted']);
    }

    public function test_a_service_exception_can_carry_field_information(): void {
        $response = $this->postJson('boom-with-fields');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'code' => 422,
            'error' => 'invalid-password',
            'fields' => ['current' => ['invalid-password']]
        ]);
    }

    public function test_a_missing_model_becomes_a_404_envelope(): void {
        $response = $this->postJson('missing');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_a_validation_failure_reports_kebab_case_rules_per_field(): void {
        $response = $this->postJson('validated', ['age' => 'not-a-number']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'code' => 422,
            'error' => 'validation-failed',
            'fields' => ['name' => ['required'], 'age' => ['integer']]
        ]);
    }

    public function test_an_unexpected_exception_becomes_a_500_envelope(): void {
        $response = $this->postJson('unexpected');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 500, 'error' => 'server-error']);
    }

    public function test_an_unexpected_exception_does_not_leak_its_message(): void {
        $response = $this->postJson('unexpected');

        $this->assertStringNotContainsString('internal detail', (string) $response->getContent());
    }

    public function test_a_throttled_request_becomes_a_429_envelope_with_status_200(): void {
        $this->postJson('limited');

        $response = $this->postJson('limited');

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 429, 'error' => 'too-many-requests']);
    }

    public function test_every_error_still_answers_with_http_200(): void {
        foreach (['boom', 'missing', 'unexpected', 'validated'] as $path) {
            $this->postJson($path)->assertStatus(200);
        }
    }

}
