<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        if (!$this->supported($endpoint)) {
            invalid('endpoint', 'unsupported-push-endpoint');
        }

        $subscription = PushSubscription::query()->where('endpoint', $endpoint)->firstOrNew();

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

        $subscription = PushSubscription::query()
            ->where('member_id', intval(actor()->requireCurrent()->getKey()))
            ->where('endpoint', $request->string('endpoint')->value())
            ->first();

        $subscription?->delete();

        return [];
    }

    private function supported(string $endpoint): bool {
        $host = parse_url($endpoint, PHP_URL_HOST);

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || !is_string($host)) {
            return false;
        }

        $hosts = config('matrix.webpush-hosts');

        foreach (is_array($hosts) ? $hosts : [] as $pattern) {
            if (is_string($pattern) && Str::is(strtolower($pattern), strtolower($host))) {
                return true;
            }
        }

        return false;
    }

}
