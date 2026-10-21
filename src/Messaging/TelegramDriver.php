<?php //>

namespace MatrixPlatform\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\TelegramSubscription;
use MatrixPlatform\Services\Messaging\TelegramService;
use MatrixPlatform\Support\CfgSchema;

/**
 * @implements Driver<TelegramLog>
 */
class TelegramDriver implements Driver {

    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 20;

    public function send(MessageLog $log): string {
        $bundle = $log->provider;
        [$chatId, $subscribed] = $this->resolve($log->chat_id);
        $sandbox = Sandbox::recipient($bundle);
        $target = $sandbox === null ? $chatId : $sandbox;

        $url = 'https://api.telegram.org/bot' . strval(cfg("{$bundle}.bot-token")) . '/sendMessage';

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->post($url, array_merge($log->data === null ? [] : $log->data, [
                    'chat_id' => $target,
                    'text' => $log->content
                ]));
        } catch (ConnectionException $exception) {
            throw new ConnectionException(str_replace($url, 'https://api.telegram.org/bot' . CfgSchema::MASK . '/sendMessage', $exception->getMessage()));
        }

        $body = $response->json();

        if (!is_array($body)) {
            error('request-failed');
        }

        $reply = Sandbox::response($sandbox, strval(json_encode($body)));

        $log->response = $reply;

        if (array_get_value($body, 'ok') === true) {
            return $reply;
        }

        $description = strval(array_get_value($body, 'description'));

        if ($description !== '') {
            $log->error = $description;
        }

        if ($sandbox === null && $subscribed && $this->expired(intval(array_get_value($body, 'error_code')), $description)) {
            $subscription = TelegramSubscription::query()->where('chat_id', $chatId)->first();
            $subscription?->delete();
        }

        error('message-refused-by-provider');
    }

    private function expired(int $code, string $description): bool {
        return $code === 403 || ($code === 400 && str_contains($description, 'chat not found'));
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function resolve(string $identifier): array {
        if (!str_starts_with($identifier, TelegramService::USER_PREFIX)) {
            return [$identifier, false];
        }

        $subscription = TelegramSubscription::query()->where('user_id', intval(substr($identifier, strlen(TelegramService::USER_PREFIX))))->first();

        if ($subscription === null) {
            error('telegram-not-subscribed');
        }

        return [$subscription->chat_id, true];
    }

}
