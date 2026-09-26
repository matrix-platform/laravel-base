<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhookCommand extends Command {

    protected $description = 'Register the configured Telegram bot webhook URL with Telegram';

    protected $signature = 'messages:telegram-webhook';

    public function handle(): int {
        $token = strval(cfg('telegram.bot-token'));
        $secret = strval(cfg('telegram.webhook-secret'));

        if ($token === '' || $secret === '') {
            $this->error('telegram.bot-token and telegram.webhook-secret must both be configured');

            return self::FAILURE;
        }

        $url = rtrim(strval(config('app.url')), '/') . '/' . trim(strval(config('matrix.api-prefix')), '/') . '/telegram/webhook';

        $response = Http::asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret
        ]);

        if ($response->json('ok') !== true) {
            $this->error('Telegram rejected the webhook registration: ' . strval($response->json('description')));

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        return self::SUCCESS;
    }

}
