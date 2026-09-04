<?php //>

namespace MatrixPlatform\Services\Messaging;

class PushService extends MessageService {

    protected string $channel = 'push';

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [
            'title' => array_get_value($rendered, 'title'),
            'data' => array_get_value($rendered, 'data')
        ];
    }

    protected function receiverKey(): string {
        return 'member_id';
    }

}
