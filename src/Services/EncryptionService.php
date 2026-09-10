<?php //>

namespace MatrixPlatform\Services;

use MatrixPlatform\Models\EncryptionKey;

class EncryptionService {

    /**
     * @return array{kid: string, public_key: string}
     */
    public function key(): array {
        $key = EncryptionKey::active();

        if ($key === null) {
            error('encryption-unavailable');
        }

        return ['kid' => $key->kid, 'public_key' => $key->public_key];
    }

}
