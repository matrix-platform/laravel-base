<?php //>

namespace MatrixPlatform\Support;

use OpenSSLAsymmetricKey;

class ApiEncryption {

    private const CIPHER = 'aes-256-gcm';

    private const CURVE = 'prime256v1';

    private const INFO = 'matrix-api-v1';

    private const IV_LENGTH = 12;

    private const KEY_LENGTH = 32;

    private const SHARED_LENGTH = 32;

    private const TAG_LENGTH = 16;

    public static function derive(string $private, string $epk): string {
        $peer = openssl_pkey_get_public(self::pem($epk));
        $own = openssl_pkey_get_private($private);

        if ($peer === false || $own === false) {
            error('invalid-envelope', 400);
        }

        $shared = openssl_pkey_derive($peer, $own, self::SHARED_LENGTH);

        if ($shared === false) {
            error('invalid-envelope', 400);
        }

        return hash_hkdf('sha256', $shared, self::KEY_LENGTH, self::INFO, '');
    }

    /**
     * @return array{public: string, private: string}
     */
    public static function generate(): array {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => self::CURVE]);

        if ($key === false || !openssl_pkey_export($key, $private)) {
            error('encryption-unavailable');
        }

        return ['public' => self::spki($key), 'private' => $private];
    }

    public static function open(string $key, string $iv, string $ct, string $aad): string {
        $nonce = base64_decode($iv, true);
        $binary = base64_decode($ct, true);

        if ($nonce === false || $binary === false || strlen($nonce) !== self::IV_LENGTH || strlen($binary) <= self::TAG_LENGTH) {
            error('invalid-envelope', 400);
        }

        $plain = openssl_decrypt(
            substr($binary, 0, -self::TAG_LENGTH),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            substr($binary, -self::TAG_LENGTH),
            $aad
        );

        if ($plain === false) {
            error('invalid-envelope', 400);
        }

        return $plain;
    }

    private static function pem(string $spki): string {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($spki, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * @return array{iv: string, ct: string}
     */
    public static function seal(string $key, string $plain, string $aad): array {
        $nonce = random_bytes(self::IV_LENGTH);
        $binary = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG_LENGTH);

        if ($binary === false) {
            error('encryption-unavailable');
        }

        return ['iv' => base64_encode($nonce), 'ct' => base64_encode($binary . $tag)];
    }

    private static function spki(OpenSSLAsymmetricKey $key): string {
        $details = openssl_pkey_get_details($key);
        $public = $details === false ? null : array_get_value($details, 'key');

        if (!is_string($public)) {
            error('encryption-unavailable');
        }

        return strval(preg_replace('/-----[^-]+-----|\s+/', '', $public));
    }

}
