<?php //>

namespace Tests\Feature\Services\Admin\Passkey;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\PasskeyCredential;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Services\Admin\Passkey\PasskeyService;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Support\PasskeyAuthenticator;

class PasskeyServiceTest extends FeatureTestCase {

    private const ORIGIN = 'https://example.com';
    private const RP_ID = 'example.com';

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.passkey-rp-id' => self::RP_ID]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function assertionRequest(PasskeyAuthenticator $authenticator, string $rpId, string $origin, ?string $userHandle = null, ?int $counterOverride = null): array {
        $options = $this->service()->authenticationOptions();
        $challenge = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $response = $authenticator->assertionResponse($challenge, $rpId, $origin, $userHandle, $counterOverride);

        return [$options['challenge'], $response];
    }

    private function captureException(callable $callback): ?ServiceException {
        try {
            $callback();
        } catch (ServiceException $exception) {
            return $exception;
        }

        return null;
    }

    private function login(PasskeyAuthenticator $authenticator, ?string $userHandle = null, ?int $counterOverride = null): User {
        [$challengeKey, $response] = $this->assertionRequest($authenticator, self::RP_ID, self::ORIGIN, $userHandle, $counterOverride);

        return $this->service()->authenticate($challengeKey, $response);
    }

    /**
     * @return list<string>
     */
    private function logType(User $user): array {
        $types = UserLog::query()
            ->where('user_id', $user->id)
            ->pluck('type')
            ->map(fn (UserLogType $type) => $type->value)
            ->all();

        return array_values($types);
    }

    private function refusesField(string $field, string $slug, callable $callback): void {
        try {
            $callback();
        } catch (ServiceException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => [$field => [$slug]]], $exception->getExtra());

            return;
        }

        $this->fail("expected the call to be refused with {$field}:{$slug}");
    }

    private function reload(PasskeyCredential $credential): PasskeyCredential {
        return PasskeyCredential::query()->findOrFail($credential->id);
    }

    /**
     * @return array{0: PasskeyAuthenticator, 1: PasskeyCredential}
     */
    private function register(User $user, ?PasskeyAuthenticator $authenticator = null): array {
        $authenticator = $authenticator === null ? new PasskeyAuthenticator() : $authenticator;
        $options = $this->service()->registrationOptions($user);
        $challenge = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $response = $authenticator->registrationResponse($challenge, self::RP_ID, self::ORIGIN);

        $credential = $this->service()->register($user, $options['challenge'], $response, 'my device');

        return [$authenticator, $credential];
    }

    private function service(): PasskeyService {
        return app(PasskeyService::class);
    }

    public function test_registration_options_include_the_users_existing_credentials_in_the_exclude_list(): void {
        $user = UserFactory::new()->createOne();
        [, $credential] = $this->register($user);

        $options = $this->service()->registrationOptions($user);

        $this->assertSame([$credential->credential_id], array_column($options['options']['excludeCredentials'], 'id'));
    }

    public function test_a_valid_attestation_response_creates_a_credential_row(): void {
        $user = UserFactory::new()->createOne();

        [$authenticator, $credential] = $this->register($user);

        $this->assertSame($user->id, $credential->user_id);
        $this->assertSame('my device', $credential->name);
        $this->assertSame(1, $credential->sign_count);
        $this->assertSame($authenticator->aaguid, $credential->aaguid);
        $this->assertSame(base64_encode($authenticator->cosePublicKey), $credential->public_key);
        $this->assertSame(['PasskeyRegistered'], $this->logType($user));
    }

    public function test_registering_the_same_credential_id_twice_is_rejected(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        $this->refusesField('credential', 'passkey-already-registered', fn () => $this->register($user, new PasskeyAuthenticator($authenticator->credentialId)));
    }

    public function test_an_expired_registration_challenge_is_rejected(): void {
        $user = UserFactory::new()->createOne();
        $options = $this->service()->registrationOptions($user);

        $this->travel((int) cfg('admin.passkey-challenge-ttl') + 1)->seconds();

        $authenticator = new PasskeyAuthenticator();
        $response = $authenticator->registrationResponse(Base64UrlSafe::decodeNoPadding($options['challenge']), self::RP_ID, self::ORIGIN);

        $this->refusesField('challenge', 'invalid-challenge', fn () => $this->service()->register($user, $options['challenge'], $response, 'my device'));
    }

    public function test_authentication_options_never_include_allow_credentials(): void {
        $user = UserFactory::new()->createOne();
        $this->register($user);

        $options = $this->service()->authenticationOptions();

        $this->assertSame([], $options['options']['allowCredentials']);
    }

    public function test_a_valid_assertion_logs_in_and_returns_the_owning_user(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        $result = $this->login($authenticator, (string) $user->id);

        $this->assertSame($user->id, $result->id);
        $this->assertSame(['PasskeyRegistered', 'Login'], $this->logType($user));
    }

    public function test_a_valid_assertion_advances_the_stored_sign_count(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator, $credential] = $this->register($user);

        $this->login($authenticator, (string) $user->id);

        $this->assertSame(2, $this->reload($credential)->sign_count);
    }

    public function test_a_replayed_sign_count_is_rejected(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        $this->login($authenticator, (string) $user->id);

        $this->refusesField('credential', 'invalid-passkey', fn () => $this->login($authenticator, (string) $user->id, counterOverride: 1));
    }

    public function test_an_authenticator_that_always_reports_zero_sign_count_can_still_log_in_repeatedly(): void {
        $user = UserFactory::new()->createOne();
        $authenticator = new PasskeyAuthenticator(alwaysZeroCounter: true);
        $this->register($user, $authenticator);

        $this->login($authenticator, (string) $user->id);
        $result = $this->login($authenticator, (string) $user->id);

        $this->assertSame($user->id, $result->id);
    }

    public function test_an_unknown_credential_and_a_tampered_signature_report_the_identical_error(): void {
        $unknown = new PasskeyAuthenticator();

        $unknownException = $this->captureException(fn () => $this->login($unknown, '1'));

        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        [$challengeKey, $response] = $this->assertionRequest($authenticator, self::RP_ID, self::ORIGIN, (string) $user->id);
        $response['response']['signature'] = base64_encode('tampered');

        $tamperedException = $this->captureException(fn () => $this->service()->authenticate($challengeKey, $response));

        $this->assertNotNull($unknownException);
        $this->assertNotNull($tamperedException);
        $this->assertSame($unknownException->getError(), $tamperedException->getError());
        $this->assertSame($unknownException->getExtra(), $tamperedException->getExtra());
    }

    public function test_a_disabled_users_credential_cannot_authenticate(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        $user->disabled = true;
        $user->save();

        $this->refusesField('credential', 'invalid-passkey', fn () => $this->login($authenticator, (string) $user->id));
    }

    public function test_wrong_origin_is_rejected(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        [$challengeKey, $response] = $this->assertionRequest($authenticator, self::RP_ID, 'https://evil.example', (string) $user->id);

        $this->refusesField('credential', 'invalid-passkey', fn () => $this->service()->authenticate($challengeKey, $response));
    }

    public function test_wrong_rp_id_is_rejected(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        [$challengeKey, $response] = $this->assertionRequest($authenticator, 'other.example', self::ORIGIN, (string) $user->id);

        $this->refusesField('credential', 'invalid-passkey', fn () => $this->service()->authenticate($challengeKey, $response));
    }

    public function test_a_failed_assertion_for_a_known_user_writes_a_passkey_login_failed_log_after_rollback(): void {
        $user = UserFactory::new()->createOne();
        [$authenticator] = $this->register($user);

        [$challengeKey, $response] = $this->assertionRequest($authenticator, self::RP_ID, self::ORIGIN, (string) $user->id);
        $response['response']['signature'] = base64_encode('tampered');

        // RollbackCallbacks only fires on the framework's TransactionRolledBack event
        // (see BaseServiceProvider::boot()), so the real transaction wrapper that
        // BaseController::callAction() provides in production must be simulated here.
        try {
            DB::transaction(fn () => $this->service()->authenticate($challengeKey, $response));
        } catch (ServiceException) {
        }

        $this->assertSame(['PasskeyRegistered', 'PasskeyLoginFailed'], $this->logType($user));
    }

    public function test_an_unknown_credential_writes_no_log_at_all(): void {
        $this->refusesField('credential', 'invalid-passkey', fn () => $this->login(new PasskeyAuthenticator(), '1'));

        $this->assertSame(0, UserLog::query()->count());
    }

    public function test_deleting_a_credential_that_belongs_to_another_user_is_not_found(): void {
        $owner = UserFactory::new()->createOne();
        [, $credential] = $this->register($owner);

        $other = UserFactory::new()->createOne();

        $this->expectException(ModelNotFoundException::class);

        $this->service()->delete($other, $credential->id);
    }

    public function test_revoke_all_removes_every_credential_for_the_user(): void {
        $user = UserFactory::new()->createOne();
        $this->register($user);
        $this->register($user, new PasskeyAuthenticator());

        $revoked = $this->service()->revokeAll($user);

        $this->assertSame(2, $revoked);
        $this->assertSame(0, PasskeyCredential::query()->where('user_id', $user->id)->count());
        $this->assertSame(['PasskeyRegistered', 'PasskeyRegistered', 'PasskeyRevoked'], $this->logType($user));
    }

}
