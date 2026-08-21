<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Support\Resources;

class Channel {

    /**
     * @param class-string<MessageLog> $model
     */
    public function __construct(
        public readonly string $name,
        public readonly string $model,
        public readonly string $queue
    ) {}

    public function provider(string $name): Provider {
        if (app(Resources::class)->getConfigBundle($name) === null) {
            error('invalid-message-provider');
        }

        return new Provider($this->name, $name);
    }

}
