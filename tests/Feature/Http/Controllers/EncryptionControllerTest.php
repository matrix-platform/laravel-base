<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Support\ApiEncryption;
use Tests\FeatureTestCase;

class EncryptionControllerTest extends FeatureTestCase {

    public function test_the_endpoint_hands_out_the_active_public_key(): void {
        $key = EncryptionKey::issue();

        $response = $this->postJson('encryption-key');

        $client = ApiEncryption::generate();

        $response->assertJson(['success' => true, 'data' => ['kid' => $key->kid, 'public_key' => $key->public_key]]);
        $this->assertSame(
            ApiEncryption::derive($key->private_key, $client['public']),
            ApiEncryption::derive($client['private'], strval($response->json('data.public_key')))
        );
    }

    public function test_the_endpoint_hands_out_the_new_key_right_after_a_rotation(): void {
        $previous = EncryptionKey::issue();

        $this->postJson('encryption-key')->assertJson(['data' => ['kid' => $previous->kid]]);

        $rotated = EncryptionKey::rotate(3600);

        $this->postJson('encryption-key')->assertJson(['success' => true, 'data' => ['kid' => $rotated->kid, 'public_key' => $rotated->public_key]]);
    }

    public function test_the_endpoint_reports_a_missing_key_ring(): void {
        $this->postJson('encryption-key')->assertJson(['success' => false, 'error' => 'encryption-unavailable']);
    }

}
