<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Support\ApiEncryption;
use Tests\FeatureTestCase;

class EncryptionControllerTest extends FeatureTestCase {

    public function test_the_endpoint_hands_out_the_active_public_key(): void {
        $key = EncryptionKey::issue();

        $response = $this->postJson('encryption-key');

        $response->assertJson(['success' => true, 'data' => ['kid' => $key->kid, 'public_key' => $key->public_key]]);
        $this->assertNotSame('', ApiEncryption::derive(ApiEncryption::generate()['private'], strval($response->json('data.public_key'))));
    }

    public function test_the_endpoint_reports_a_missing_key_ring(): void {
        $this->postJson('encryption-key')->assertJson(['success' => false, 'error' => 'encryption-unavailable']);
    }

    // It sits outside every prefix on purpose: it is what hands out the key, so no switch may ever seal it.
    public function test_the_endpoint_stays_readable_while_every_switch_is_on(): void {
        config([
            'matrix.admin-api-encryption' => true,
            'matrix.api-encryption' => true,
            'matrix.vendor-api-encryption' => true
        ]);

        EncryptionKey::issue();

        $this->postJson('encryption-key')->assertJson(['success' => true]);
    }

}
