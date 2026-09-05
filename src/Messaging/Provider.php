<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;

class Provider {

    public function __construct(
        public readonly string $channel,
        public readonly string $name
    ) {}

    /**
     * @return Driver<MessageLog>|null
     */
    public function driver(): ?Driver {
        return resolve_driver($this->name, Driver::class, 'invalid-message-driver');
    }

}
