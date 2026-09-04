<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\PushSubscription;

class MemberPushSubscriptionController extends BaseController {

    /**
     * @return array{id: mixed}
     */
    #[Action]
    public function subscribe(Request $request): array {
        $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string']
        ]);

        $endpoint = $request->string('endpoint')->value();
        $subscription = PushSubscription::query()->where('endpoint', $endpoint)->first() ?? new PushSubscription();

        $subscription->member_id = intval(actor()->requireCurrent()->getKey());
        $subscription->endpoint = $endpoint;
        $subscription->p256dh = $request->string('keys.p256dh')->value();
        $subscription->auth = $request->string('keys.auth')->value();

        $subscription->save();

        return ['id' => $subscription->id];
    }

    /**
     * @return array{}
     */
    #[Action]
    public function unsubscribe(Request $request): array {
        $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::query()
            ->where('member_id', intval(actor()->requireCurrent()->getKey()))
            ->where('endpoint', $request->string('endpoint')->value())
            ->delete();

        return [];
    }

}
