<?php //>

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Http\Controllers\Admin\DriveController;
use MatrixPlatform\Http\Controllers\Admin\FileController;
use MatrixPlatform\Http\Controllers\TelegramWebhookController;
use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Support\ApiEncryption;
use MatrixPlatform\Support\PackageRegistry;
use ReflectionMethod;
use Tests\FeatureTestCase;
use Tests\Stubs\StubController;

class EncryptedEnvelopeMiddlewareTest extends FeatureTestCase {

    private string $aad = '';

    private string $secret = '';

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void {
        parent::defineEnvironment($app);

        $app['config']->set('matrix.admin-api-encryption', true);
    }

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void {
        $router
            ->prefix('admin')
            ->middleware(['encrypted-api', 'envelope-api'])
            ->group(fn () => ActionRoutes::scan(StubController::class));

        $router
            ->prefix('api')
            ->middleware(['encrypted-api', 'envelope-api'])
            ->group(fn () => ActionRoutes::scan(StubController::class));

        $router
            ->prefix('vendor')
            ->middleware(['encrypted-api', 'envelope-api'])
            ->group(fn () => ActionRoutes::scan(StubController::class));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function envelope(string $path, array $body = [], ?int $timestamp = null): array {
        $server = EncryptionKey::active();

        $this->assertNotNull($server);

        $moment = $timestamp === null ? now()->getTimestamp() : $timestamp;
        $ephemeral = ApiEncryption::generate();

        $this->secret = ApiEncryption::derive($ephemeral['private'], $server->public_key);
        $this->aad = "response|POST {$path}|{$moment}";

        $sealed = ApiEncryption::seal($this->secret, strval(json_encode($body)), "request|POST {$path}|{$moment}");

        return ['kid' => $server->kid, 'epk' => $ephemeral['public'], 'ts' => $moment, 'iv' => $sealed['iv'], 'ct' => $sealed['ct']];
    }

    /**
     * @param TestResponse<JsonResponse> $response
     * @return array<string, mixed>
     */
    private function opened(TestResponse $response): array {
        $body = json_decode(strval($response->getContent()), true);

        $this->assertIsArray($body);
        $this->assertSame(['iv', 'ct'], array_keys($body));

        $plain = ApiEncryption::open($this->secret, strval($body['iv']), strval($body['ct']), $this->aad);
        $decoded = json_decode($plain, true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function test_an_encrypted_request_reaches_the_controller_as_plain_json(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/validated', ['name' => 'matrix', 'age' => 7]);
        $response = $this->postJson('admin/validated', $envelope);

        $response->assertStatus(200);
        $this->assertSame(['success' => true, 'data' => 'ok'], $this->opened($response));
    }

    public function test_the_response_body_carries_no_plain_text(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/validated', ['name' => 'matrix', 'age' => 7]);
        $response = $this->postJson('admin/validated', $envelope);

        $this->assertStringNotContainsString('success', strval($response->getContent()));
    }

    public function test_an_error_envelope_from_the_inner_middleware_is_encrypted_too(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/boom');
        $response = $this->postJson('admin/boom', $envelope);

        $this->assertSame(
            ['success' => false, 'code' => 409, 'error' => 'data-conflicted', 'message' => 'Data has been modified by someone else'],
            $this->opened($response)
        );
    }

    public function test_a_validation_failure_survives_the_round_trip(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/validated', ['age' => 'not-a-number']);
        $response = $this->postJson('admin/validated', $envelope);

        $opened = $this->opened($response);

        $this->assertSame(422, $opened['code']);
        $this->assertSame(['name' => ['required'], 'age' => ['integer']], $opened['fields']);
    }

    public function test_an_unknown_kid_is_rejected_in_plain_text(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain');
        $envelope['kid'] = 'not-a-known-kid';

        $response = $this->postJson('admin/plain', $envelope);

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_an_expired_kid_is_rejected(): void {
        $key = EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain');

        $key->expire_time = now()->subSecond();
        $key->save();

        $this->postJson('admin/plain', $envelope)->assertJson(['success' => false, 'error' => 'invalid-envelope']);
    }

    public function test_a_key_inside_its_grace_period_still_decrypts(): void {
        $key = EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain');

        $key->expire_time = now()->addHour();
        $key->save();

        $this->assertSame(
            ['success' => true, 'data' => 'plain'],
            $this->opened($this->postJson('admin/plain', $envelope))
        );
    }

    public function test_a_tampered_ciphertext_is_rejected(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/validated', ['name' => 'matrix', 'age' => 7]);
        $binary = strval(base64_decode(strval($envelope['ct']), true));
        $binary[0] = $binary[0] === 'a' ? 'b' : 'a';
        $envelope['ct'] = base64_encode($binary);

        $this->postJson('admin/validated', $envelope)->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_an_envelope_cannot_be_replayed_to_another_endpoint(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain');

        $this->postJson('admin/ping-pong', $envelope)->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_the_same_envelope_cannot_be_sent_twice(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain');

        $this->postJson('admin/plain', $envelope);

        $this->postJson('admin/plain', $envelope)->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_a_stale_timestamp_is_rejected(): void {
        EncryptionKey::issue();

        $envelope = $this->envelope('admin/plain', [], now()->getTimestamp() - 1000);

        $this->postJson('admin/plain', $envelope)->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_a_plain_request_is_rejected_while_encryption_is_enabled(): void {
        EncryptionKey::issue();

        $this->postJson('admin/plain')->assertJson(['success' => false, 'code' => 400, 'error' => 'invalid-envelope']);
    }

    public function test_an_action_declared_plain_is_left_alone(): void {
        EncryptionKey::issue();

        $this->postJson('admin/unsealed')->assertJson(['success' => true, 'data' => 'unsealed']);
    }

    public function test_the_public_api_prefix_stays_plain_while_its_own_switch_is_off(): void {
        EncryptionKey::issue();

        $this->postJson('api/plain')->assertJson(['success' => true, 'data' => 'plain']);
    }

    public function test_the_vendor_prefix_seals_on_its_own_switch(): void {
        EncryptionKey::issue();

        config()->set('matrix.vendor-api-encryption', true);

        $envelope = $this->envelope('vendor/plain');

        $this->assertSame(
            ['success' => true, 'data' => 'plain'],
            $this->opened($this->postJson('vendor/plain', $envelope))
        );
    }

    // Deliberate decisions, not defaults anyone should flip without noticing.
    public function test_the_shipped_defaults_seal_admin_and_vendor_but_leave_the_public_api_plain(): void {
        $shipped = require app(PackageRegistry::class)->path('base') . '/config/matrix.php';

        $this->assertSame(
            ['admin' => true, 'api' => false, 'vendor' => true],
            [
                'admin' => $shipped['admin-api-encryption'],
                'api' => $shipped['api-encryption'],
                'vendor' => $shipped['vendor-api-encryption']
            ]
        );
    }

    // Callers that can never seal an envelope: Telegram's server, and the two multipart uploads the browser sends as FormData.
    // Losing any of these marks turns a working endpoint into invalid-envelope the moment its group's switch is on.
    public function test_the_callers_that_cannot_seal_an_envelope_are_declared_plain(): void {
        $actions = [
            'telegram' => [TelegramWebhookController::class, 'webhook'],
            'file-upload' => [FileController::class, 'upload'],
            'drive-upload' => [DriveController::class, 'upload']
        ];
        $declared = [];

        foreach ($actions as $name => [$controller, $method]) {
            $declared[$name] = ActionRoutes::attribute(new ReflectionMethod($controller, $method))?->encrypted;
        }

        $this->assertSame(['telegram' => false, 'file-upload' => false, 'drive-upload' => false], $declared);
    }

    public function test_the_switch_keeps_every_request_plain_while_it_is_off(): void {
        EncryptionKey::issue();

        config()->set('matrix.admin-api-encryption', false);

        $this->postJson('admin/plain')->assertJson(['success' => true, 'data' => 'plain']);
    }

}
