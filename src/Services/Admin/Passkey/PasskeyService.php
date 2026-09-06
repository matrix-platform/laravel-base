<?php //>

namespace MatrixPlatform\Services\Admin\Passkey;

use Cose\Algorithms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Models\PasskeyCredential;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Support\RollbackCallbacks;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatement;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class PasskeyService {

    private static ?SerializerInterface $serializer = null;

    /**
     * @param array<string, mixed> $credential
     */
    public function authenticate(string $challenge, array $credential): User {
        $options = Cache::pull("passkey-login:{$challenge}");

        if (!$options instanceof PublicKeyCredentialRequestOptions) {
            invalid('credential', 'invalid-passkey');
        }

        try {
            $publicKeyCredential = $this->serializer()->deserialize(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');

            if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
                throw new RuntimeException('Not an assertion response.');
            }
        } catch (Throwable) {
            invalid('credential', 'invalid-passkey');
        }

        $stored = PasskeyCredential::query()->where('credential_id', Base64UrlSafe::encodeUnpadded($publicKeyCredential->rawId))->first();

        if ($stored === null) {
            invalid('credential', 'invalid-passkey');
        }

        $user = User::query()->whereEnabled()->find($stored->user_id);

        if ($user === null) {
            invalid('credential', 'invalid-passkey');
        }

        $record = CredentialRecord::create(
            Base64UrlSafe::decodeNoPadding($stored->credential_id),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            [],
            AttestationStatement::TYPE_NONE,
            EmptyTrustPath::create(),
            Uuid::fromString($stored->aaguid),
            base64_decode($stored->public_key),
            (string) $user->id,
            $stored->sign_count,
            null,
            null,
            null,
            $stored->uv_initialized
        );

        try {
            $result = AuthenticatorAssertionResponseValidator::create($this->ceremonies()->requestCeremony())
                ->check($record, $publicKeyCredential->response, $options, request()->getHost(), null);
        } catch (Throwable) {
            app(RollbackCallbacks::class)->register(fn () => $user->writeLog(UserLogType::PasskeyLoginFailed));

            invalid('credential', 'invalid-passkey');
        }

        $stored->sign_count = $result->counter;
        $stored->uv_initialized = $result->uvInitialized;
        $stored->last_used_time = now();
        $stored->save();

        $user->writeLog(UserLogType::Login);

        return $user;
    }

    /**
     * @return array{options: array<string, mixed>, challenge: string}
     */
    public function authenticationOptions(): array {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            rpId: $this->rpId(),
            allowCredentials: [],
            userVerification: 'required',
            timeout: $this->timeout()
        );

        $challengeKey = $this->challengeKey($challenge);

        Cache::put("passkey-login:{$challengeKey}", $options, (int) cfg('admin.passkey-challenge-ttl'));

        return ['options' => $this->authenticationOptionsJson($options), 'challenge' => $challengeKey];
    }

    /**
     * @return Collection<int, PasskeyCredential>
     */
    public function credentials(User $user): Collection {
        $query = $this->credentialsOf($user);

        return $query->orderByDesc('last_used_time')->get();
    }

    public function delete(User $user, int $id): void {
        $credential = $this->credentialsOf($user)->findOrFail($id);

        $credential->delete();

        $user->writeLog(UserLogType::PasskeyRevoked);
    }

    /**
     * @param array<string, mixed> $credential
     */
    public function register(User $user, string $challenge, array $credential, string $name): PasskeyCredential {
        $options = Cache::pull("passkey-registration:{$challenge}");

        if (!$options instanceof PublicKeyCredentialCreationOptions) {
            invalid('challenge', 'invalid-challenge');
        }

        try {
            $publicKeyCredential = $this->serializer()->deserialize(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');

            if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
                throw new RuntimeException('Not an attestation response.');
            }

            $record = AuthenticatorAttestationResponseValidator::create($this->ceremonies()->creationCeremony())
                ->check($publicKeyCredential->response, $options, request()->getHost());
        } catch (Throwable) {
            invalid('credential', 'invalid-passkey');
        }

        $credentialId = Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId);

        if (PasskeyCredential::query()->where('credential_id', $credentialId)->exists()) {
            invalid('credential', 'passkey-already-registered');
        }

        $model = new PasskeyCredential();

        $model->user_id = $user->id;
        $model->credential_id = $credentialId;
        $model->public_key = base64_encode($record->credentialPublicKey);
        $model->aaguid = (string) $record->aaguid;
        $model->sign_count = $record->counter;
        $model->uv_initialized = $record->uvInitialized;
        $model->name = $name;

        $model->save();

        $user->writeLog(UserLogType::PasskeyRegistered);

        return $model;
    }

    /**
     * @return array{options: array<string, mixed>, challenge: string}
     */
    public function registrationOptions(User $user): array {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('', $this->rpId()),
            PublicKeyCredentialUserEntity::create($user->username, (string) $user->id, $user->username),
            $challenge,
            [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256)
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: 'required',
                residentKey: 'required'
            ),
            attestation: 'none',
            excludeCredentials: $this->credentialsOf($user)
                ->pluck('credential_id')
                ->map(fn (string $id) => PublicKeyCredentialDescriptor::create(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, Base64UrlSafe::decodeNoPadding($id)))
                ->all(),
            timeout: $this->timeout()
        );

        $challengeKey = $this->challengeKey($challenge);

        Cache::put("passkey-registration:{$challengeKey}", $options, (int) cfg('admin.passkey-challenge-ttl'));

        return ['options' => $this->registrationOptionsJson($options), 'challenge' => $challengeKey];
    }

    public function rename(User $user, int $id, string $name): void {
        $credential = $this->credentialsOf($user)->findOrFail($id);

        $credential->name = $name;
        $credential->save();
    }

    public function revokeAll(User $user): int {
        $credentials = $this->credentialsOf($user)->get();

        foreach ($credentials as $credential) {
            $credential->delete();
        }

        if ($credentials->isNotEmpty()) {
            $user->writeLog(UserLogType::PasskeyRevoked);
        }

        return $credentials->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticationOptionsJson(PublicKeyCredentialRequestOptions $options): array {
        return [
            'allowCredentials' => array_map($this->descriptorJson(...), $options->allowCredentials),
            'challenge' => Base64UrlSafe::encodeUnpadded($options->challenge),
            'rpId' => $options->rpId,
            'timeout' => $options->timeout,
            'userVerification' => $options->userVerification
        ];
    }

    private function ceremonies(): CeremonyStepManagerFactory {
        $factory = new CeremonyStepManagerFactory();
        $factory->setCounterChecker(new PasskeyCounterChecker());
        $factory->setAllowedOrigins(["https://{$this->rpId()}"], (bool) cfg('admin.passkey-allow-subdomains'));

        return $factory;
    }

    private function challengeKey(string $challenge): string {
        return Base64UrlSafe::encodeUnpadded($challenge);
    }

    /**
     * @return Builder<PasskeyCredential>
     */
    private function credentialsOf(User $user): Builder {
        return PasskeyCredential::query()->where('user_id', $user->id);
    }

    /**
     * @return array{type: string, id: string, transports: list<string>}
     */
    private function descriptorJson(PublicKeyCredentialDescriptor $descriptor): array {
        return ['type' => $descriptor->type, 'id' => Base64UrlSafe::encodeUnpadded($descriptor->id), 'transports' => array_values($descriptor->transports)];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationOptionsJson(PublicKeyCredentialCreationOptions $options): array {
        return [
            'attestation' => $options->attestation,
            'authenticatorSelection' => $options->authenticatorSelection === null ? null : [
                'authenticatorAttachment' => $options->authenticatorSelection->authenticatorAttachment,
                'requireResidentKey' => $options->authenticatorSelection->requireResidentKey,
                'residentKey' => $options->authenticatorSelection->residentKey,
                'userVerification' => $options->authenticatorSelection->userVerification
            ],
            'challenge' => Base64UrlSafe::encodeUnpadded($options->challenge),
            'excludeCredentials' => array_map($this->descriptorJson(...), $options->excludeCredentials),
            'pubKeyCredParams' => array_map(fn (PublicKeyCredentialParameters $p) => ['type' => $p->type, 'alg' => $p->alg], $options->pubKeyCredParams),
            'rp' => ['id' => $options->rp->id, 'name' => $this->rpName()],
            'timeout' => $options->timeout,
            'user' => [
                'displayName' => $options->user->displayName,
                'id' => Base64UrlSafe::encodeUnpadded($options->user->id),
                'name' => $options->user->name
            ]
        ];
    }

    private function rpId(): string {
        $configured = config('matrix.passkey-rp-id');

        return is_string($configured) && $configured !== '' ? $configured : request()->getHost();
    }

    private function rpName(): string {
        return i18n('backend.app.name');
    }

    private function serializer(): SerializerInterface {
        if (self::$serializer === null) {
            self::$serializer = (new WebauthnSerializerFactory(new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])))->create();
        }

        return self::$serializer;
    }

    /**
     * @return positive-int
     */
    private function timeout(): int {
        $timeout = (int) cfg('admin.passkey-timeout');

        if ($timeout < 1) {
            return 1;
        }

        return $timeout;
    }

}
