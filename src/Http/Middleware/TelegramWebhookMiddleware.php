<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TelegramWebhookMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        $secret = strval(cfg('telegram.webhook-secret'));

        if ($secret === '' || !hash_equals($secret, strval($request->header('X-Telegram-Bot-Api-Secret-Token')))) {
            error('permission-denied', 403);
        }

        return $next($request);
    }

}
