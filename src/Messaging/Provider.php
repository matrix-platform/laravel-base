<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;

class Provider {

    public function __construct(
        public readonly string $channel,
        public readonly string $name
    ) {}

    public function bundle(): string {
        return "{$this->channel}/{$this->name}";
    }

    /**
     * @return Driver<MessageLog>|null
     */
    public function driver(): ?Driver {
        $class = cfg("{$this->bundle()}.driver");

        if ($class === null) {
            return null;
        }

        if (!is_string($class) || !is_a($class, Driver::class, true)) {
            error('invalid-message-driver');
        }

        return app($class);
    }

}
