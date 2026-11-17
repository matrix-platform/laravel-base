<?php //>

namespace Tests\Feature\Models;

use MatrixPlatform\Models\EncryptionKey;
use Tests\FeatureTestCase;

class EncryptionKeyTest extends FeatureTestCase {

    public function test_a_missing_or_blank_kid_finds_no_key(): void {
        $key = EncryptionKey::issue();

        $key->kid = '';

        $key->save();

        $this->assertNull(EncryptionKey::findByKid(''));
        $this->assertNull(EncryptionKey::findByKid(null));
    }

}
