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
        $class = cfg("{$this->name}.driver");

        if ($class === null) {
            return null;
        }

        if (!is_string($class) || !is_a($class, Driver::class, true)) {
            error('invalid-message-driver');
        }

        return app($class);
    }

}
