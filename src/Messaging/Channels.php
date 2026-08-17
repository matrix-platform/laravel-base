<?php //>

namespace MatrixPlatform\Messaging;

use MatrixPlatform\Models\MessageLog;

class Channels {

    public function get(string $name): Channel {
        $config = array_get_value($this->all(), $name);

        if (!is_array($config)) {
            error('unknown-message-channel');
        }

        $model = array_get_value($config, 'model');

        if (!is_string($model) || !is_a($model, MessageLog::class, true)) {
            error('invalid-message-channel');
        }

        return new Channel($name, $model);
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return array_map(strval(...), array_keys($this->all()));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function all(): array {
        $channels = config('matrix.messaging.channels');

        return is_array($channels) ? $channels : [];
    }

}
