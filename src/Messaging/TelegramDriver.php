<?php //>

namespace MatrixPlatform\Messaging;

use Illuminate\Support\Facades\Http;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\TelegramSubscription;

/**
 * @implements Driver<TelegramLog>
 */
class TelegramDriver implements Driver {

    public function send(MessageLog $log): string {
        $bundle = $log->provider;
        [$chatId, $subscribed] = $this->resolve($log->chat_id);
        $sandbox = Sandbox::recipient($bundle);
        $target = $sandbox === null ? $chatId : $sandbox;

        $response = Http::post('https://api.telegram.org/bot' . strval(cfg("{$bundle}.bot-token")) . '/sendMessage', array_merge([
            'chat_id' => $target,
            'text' => $log->content
        ], $log->data === null ? [] : $log->data));

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
        if (intval($identifier) < 0) {
            return [$identifier, false];
        }

        $subscription = TelegramSubscription::query()->where('user_id', $identifier)->first();

        return $subscription === null ? [$identifier, false] : [$subscription->chat_id, true];
    }

}
