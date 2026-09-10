<?php //>

namespace MatrixPlatform\Http\Controllers;

use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\EncryptionService;

class EncryptionController extends BaseController {

    public function __construct(private EncryptionService $service) {}

    /**
     * @return array{kid: string, public_key: string}
     */
    #[Action('encryption-key', encrypted: false)]
    public function key(): array {
        return $this->service->key();
    }

}
