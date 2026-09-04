<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\TelegramSubscription;

class TelegramWebhookController extends BaseController {

    /**
     * @return array{}
     */
    #[Action]
    public function webhook(Request $request): array {
        $matches = [];

        if (preg_match('#^/start\s+(\S+)$#', strval($request->input('message.text')), $matches) === 1) {
            $this->bind($request, $matches[1]);
        }

        return [];
    }

    private function bind(Request $request, string $token): void {
        $userId = Cache::pull("telegram-link:{$token}");

        if ($userId === null) {
            return;
        }

        $userId = intval($userId);
        $chatId = strval($request->input('message.chat.id'));
        $username = $request->input('message.from.username');

        if (TelegramSubscription::query()->where('chat_id', $chatId)->where('user_id', '!=', $userId)->exists()) {
            Log::error('messaging.telegram.bind-conflict', ['user_id' => $userId, 'chat_id' => $chatId]);

            return;
        }

        $found = TelegramSubscription::query()->where('user_id', $userId)->first();
        $subscription = $found === null ? new TelegramSubscription() : $found;

        $subscription->user_id = $userId;
        $subscription->chat_id = $chatId;
        $subscription->username = is_string($username) ? $username : null;

        $subscription->save();
    }

}
