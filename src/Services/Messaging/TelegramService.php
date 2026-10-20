<?php //>

namespace MatrixPlatform\Services\Messaging;

use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\User;

class TelegramService extends MessageService {

    const USER_PREFIX = 'user:';

    protected string $channel = 'telegram';

    /**
     * @param array<string, string> $vars
     * @param array<string, mixed> $options
     */
    public function sendToUser(User|int $user, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        return $this->send(self::USER_PREFIX . ($user instanceof User ? $user->id : $user), $template, $vars, $options);
    }

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
