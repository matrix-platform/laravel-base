<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Support\CfgSchema;

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

        $endpoint = "https://api.telegram.org/bot{$token}/setWebhook";

        try {
            $response = Http::asForm()->post($endpoint, [
                'url' => $url,
                'secret_token' => $secret
            ]);
        } catch (ConnectionException $exception) {
            $this->error('Could not reach the Telegram API: ' . str_replace($endpoint, 'https://api.telegram.org/bot' . CfgSchema::MASK . '/setWebhook', $exception->getMessage()));

            return self::FAILURE;
        }

        if ($response->json('ok') !== true) {
            $this->error('Telegram rejected the webhook registration: ' . strval($response->json('description')));

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        return self::SUCCESS;
    }

}
