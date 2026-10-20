<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\User;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class TranslationControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function translate(array $input, ?string $token = null): TestResponse {
        return $this->withToken($token === null ? $this->token : $token)->postJson('admin/translation', $input);
    }

    public function test_a_configured_provider_reports_the_translated_content(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        $response = $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'tw']);

        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.content', 'translated: Hello');
    }

    public function test_a_provider_with_no_driver_configured_reports_unavailable(): void {
        config()->set('matrix.translation-provider', 'does-not-exist');

        $response = $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'tw']);

        $response->assertJsonPath('data.available', false);
    }

    public function test_a_provider_without_an_api_key_reports_unavailable_without_any_request(): void {
        Http::fake();

        config()->set('matrix.translation-provider', 'google-translate');

        $response = $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'tw']);

        $response->assertJsonPath('data.available', false);

        Http::assertNothingSent();
    }

    public function test_a_provider_that_cannot_be_reached_answers_translation_request_failed(): void {
        $this->useCfg('google-translate', ['api-key' => 'test-key']);

        config()->set('matrix.translation-provider', 'google-translate');

        Http::fake(fn () => throw new ConnectionException('unreachable'));

        $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'tw'])->assertJson(['success' => false, 'error' => 'translation-request-failed']);
    }

    public function test_a_source_locale_outside_the_configured_list_is_refused_as_a_validation_error(): void {
        $response = $this->translate(['content' => 'Hello', 'source' => 'fr', 'target' => 'tw']);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.source.0', fn (mixed $value): bool => is_string($value));
    }

    public function test_a_target_locale_equal_to_the_source_locale_is_refused_as_a_validation_error(): void {
        $response = $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'en']);

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
        $response->assertJsonPath('fields.target.0', 'different');
    }

    public function test_a_regular_user_without_the_permission_granted_is_refused(): void {
        $token = UserFactory::new()->createOne(['id' => 1001])->createToken();

        $response = $this->translate(['content' => 'Hello', 'source' => 'en', 'target' => 'tw'], $token);

        $response->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
