<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class GeoLocationControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->useGeolocationFixtures();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function locate(array $input, ?string $token = null): TestResponse {
        return $this->withToken($token === null ? $this->token : $token)->postJson('admin/geolocation', $input);
    }

    public function test_a_resolvable_ip_reports_the_mapped_location(): void {
        config()->set('matrix.geolocation-provider', 'stub');

        $response = $this->locate(['ip' => '8.8.8.8']);

        $response->assertJsonPath('data.found', true);
        $response->assertJsonPath('data.location.country_code', 'US');
    }

    public function test_an_ip_the_driver_cannot_resolve_reports_not_found(): void {
        config()->set('matrix.geolocation-provider', 'empty');

        $response = $this->locate(['ip' => '8.8.8.8']);

        $response->assertJsonPath('data.found', false);
    }

    public function test_an_invalid_ip_format_is_refused_as_a_validation_error(): void {
        $response = $this->locate(['ip' => 'not-an-ip']);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.ip.0', 'ip');
    }

    public function test_a_regular_user_without_the_permission_granted_is_refused(): void {
        $token = UserFactory::new()->createOne(['id' => 1001])->createToken();

        $response = $this->locate(['ip' => '8.8.8.8'], $token);

        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
