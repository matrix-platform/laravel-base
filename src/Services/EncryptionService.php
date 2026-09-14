<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Models\EncryptionKey;

class EncryptionService {

    /**
     * @return array{kid: string, public_key: string}
     */
    public function key(): array {
        return Cache::remember(EncryptionKey::ACTIVE_CACHE_KEY, EncryptionKey::ACTIVE_CACHE_TTL, function () {
            $key = EncryptionKey::active();

            if ($key === null) {
                error('encryption-unavailable');
            }

            return ['kid' => $key->kid, 'public_key' => $key->public_key];
        });
    }

}
