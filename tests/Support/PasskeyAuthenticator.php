<?php //>

namespace Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapItem;
use CBOR\MapObject;
use CBOR\TextStringObject;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Webauthn\U2FPublicKey;

/**
 * A minimal software WebAuthn authenticator used to produce cryptographically
 * valid registration/assertion responses in tests, without a real browser.
 */
final class PasskeyAuthenticator {

    public readonly string $aaguid;
    public readonly string $cosePublicKey;
    public readonly string $credentialId;

    private readonly OpenSSLAsymmetricKey $privateKey;
    private int $signCount = 0;

    public function __construct(?string $credentialId = null, ?string $aaguid = null, private readonly bool $alwaysZeroCounter = false) {
        $this->credentialId = $credentialId === null ? random_bytes(32) : $credentialId;
        $this->aaguid = $aaguid === null ? (string) Uuid::v4() : $aaguid;

        $keyPair = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ]);

        if ($keyPair === false) {
            throw new RuntimeException('Unable to generate an EC key pair: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($keyPair);

        if ($details === false) {
            throw new RuntimeException('Unable to read the EC key pair details: ' . openssl_error_string());
        }

        $x = str_pad((string) $details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $this->privateKey = $keyPair;
        $this->cosePublicKey = U2FPublicKey::convertToCoseKey("\x04{$x}{$y}");
    }

    /**
     * @return array{id: string, rawId: string, type: string, response: array{clientDataJSON: string, signature: string, authenticatorData: string, userHandle: ?string}}
     */
    public function assertionResponse(string $challenge, string $rpId, string $origin, ?string $userHandle = null, ?int $counterOverride = null): array {
        $clientData = self::clientData('webauthn.get', $challenge, $origin);
        $authData = $this->authData($rpId, includeAttestedCredentialData: false, counterOverride: $counterOverride);

        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [
            'id' => self::base64url($this->credentialId),
            'rawId' => self::base64url($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'authenticatorData' => base64_encode($authData),
                'clientDataJSON' => self::base64url($clientData),
                'signature' => base64_encode((string) $signature),
                'userHandle' => $userHandle === null ? null : base64_encode($userHandle)
            ]
        ];
    }

    /**
     * @return array{id: string, rawId: string, type: string, response: array{clientDataJSON: string, attestationObject: string, transports: list<string>}}
     */
    public function registrationResponse(string $challenge, string $rpId, string $origin): array {
        $clientData = self::clientData('webauthn.create', $challenge, $origin);
        $authData = $this->authData($rpId, includeAttestedCredentialData: true);

        $attestationObject = (string) MapObject::create([
            MapItem::create(TextStringObject::create('fmt'), TextStringObject::create('none')),
            MapItem::create(TextStringObject::create('attStmt'), MapObject::create([])),
            MapItem::create(TextStringObject::create('authData'), ByteStringObject::create($authData))
        ]);

        return [
            'id' => self::base64url($this->credentialId),
            'rawId' => self::base64url($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'attestationObject' => base64_encode($attestationObject),
                'clientDataJSON' => self::base64url($clientData),
                'transports' => ['internal']
            ]
        ];
    }

    private function authData(string $rpId, bool $includeAttestedCredentialData, ?int $counterOverride = null): string {
        $flags = 0x01 | 0x04 | ($includeAttestedCredentialData ? 0x40 : 0x00); // UP | UV [| AT]
        $counter = match (true) {
            $counterOverride !== null => $counterOverride,
            $this->alwaysZeroCounter => 0,
            default => ++$this->signCount
        };

        $authData = hash('sha256', $rpId, true) . chr($flags) . pack('N', $counter);

        if ($includeAttestedCredentialData) {
            $authData .= Uuid::fromString($this->aaguid)->toBinary()
                . pack('n', strlen($this->credentialId))
                . $this->credentialId
                . $this->cosePublicKey;
        }

        return $authData;
    }

    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return string
     */
    private static function clientData(string $type, string $challenge, string $origin): string {
        return json_encode([
            'type' => $type,
            'challenge' => self::base64url($challenge),
            'origin' => $origin
        ], JSON_THROW_ON_ERROR);
    }

}
