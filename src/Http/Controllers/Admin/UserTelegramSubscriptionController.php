<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\TelegramSubscription;

class UserTelegramSubscriptionController extends BaseController {

    /**
     * @return array{url: string}
     */
    #[Action]
    public function link(): array {
        $token = Str::random();

        Cache::put("telegram-link:{$token}", intval(actor()->requireCurrent()->getKey()), now()->addMinutes(10));

        return ['url' => 'https://t.me/' . strval(cfg('telegram.bot-username')) . "?start={$token}"];
    }

    /**
     * @return array{}
     */
    #[Action]
    public function unsubscribe(): array {
        $subscription = TelegramSubscription::query()->where('user_id', intval(actor()->requireCurrent()->getKey()))->first();
        $subscription?->delete();

        return [];
    }

}
