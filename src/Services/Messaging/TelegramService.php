<?php //>

namespace MatrixPlatform\Services\Messaging;

class TelegramService extends MessageService {

    protected string $channel = 'telegram';

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [
            'data' => array_get_value($rendered, 'data')
        ];
    }

    protected function receiverKey(): string {
        return 'chat_id';
    }

}
