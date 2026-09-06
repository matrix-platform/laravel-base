<?php //>

namespace Tests\Unit\Support;

use Cose\Algorithms;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PasskeyAuthenticator;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyAuthenticatorTest extends TestCase {

    private const ORIGIN = 'https://example.com';
    private const RP_ID = 'example.com';

    public function test_a_registration_response_passes_the_real_attestation_validator(): void {
        $challenge = random_bytes(32);
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test RP', self::RP_ID),
            PublicKeyCredentialUserEntity::create('alice', '1', 'Alice'),
            $challenge,
            [PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256)],
            attestation: 'none'
        );

        $authenticator = new PasskeyAuthenticator();
        $credential = $this->deserialize($authenticator->registrationResponse($challenge, self::RP_ID, self::ORIGIN));
        $response = $credential->response;

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException('expected an attestation response');
        }

        $validator = AuthenticatorAttestationResponseValidator::create($this->factory()->creationCeremony());
        $record = $validator->check($response, $options, self::RP_ID);

        $this->assertSame($authenticator->credentialId, $record->publicKeyCredentialId);
        $this->assertSame($authenticator->cosePublicKey, $record->credentialPublicKey);
        $this->assertSame($authenticator->aaguid, (string) $record->aaguid);
        $this->assertSame(1, $record->counter);
    }

    public function test_an_assertion_response_passes_the_real_assertion_validator_and_advances_the_counter(): void {
        $registrationChallenge = random_bytes(32);
        $creationOptions = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Test RP', self::RP_ID),
            PublicKeyCredentialUserEntity::create('alice', '1', 'Alice'),
            $registrationChallenge,
            [PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256)],
            attestation: 'none'
        );

        $authenticator = new PasskeyAuthenticator();
        $registration = $this->deserialize($authenticator->registrationResponse($registrationChallenge, self::RP_ID, self::ORIGIN));
        $registrationResponse = $registration->response;

        if (!$registrationResponse instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException('expected an attestation response');
        }

        $record = AuthenticatorAttestationResponseValidator::create($this->factory()->creationCeremony())
            ->check($registrationResponse, $creationOptions, self::RP_ID);

        $loginChallenge = random_bytes(32);
        $requestOptions = PublicKeyCredentialRequestOptions::create($loginChallenge, rpId: self::RP_ID, userVerification: 'required');

        $assertion = $this->deserialize($authenticator->assertionResponse($loginChallenge, self::RP_ID, self::ORIGIN, '1'));
        $assertionResponse = $assertion->response;

        if (!$assertionResponse instanceof AuthenticatorAssertionResponse) {
            throw new RuntimeException('expected an assertion response');
        }

        $validator = AuthenticatorAssertionResponseValidator::create($this->factory()->requestCeremony());
        $result = $validator->check($record, $assertionResponse, $requestOptions, self::RP_ID, null);

        $this->assertSame(2, $result->counter);
    }

    /**
     * @param array<string, mixed> $credential
     */
    private function deserialize(array $credential): PublicKeyCredential {
        $serializer = (new WebauthnSerializerFactory(new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])))->create();

        return $serializer->deserialize(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');
    }

    private function factory(): CeremonyStepManagerFactory {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([self::ORIGIN]);

        return $factory;
    }

}
