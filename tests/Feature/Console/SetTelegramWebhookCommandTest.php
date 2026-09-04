<?php //>

namespace Tests\Feature\Console;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\FeatureTestCase;

class SetTelegramWebhookCommandTest extends FeatureTestCase {

    public function test_a_missing_bot_token_or_secret_fails_without_calling_telegram(): void {
        Http::fake();

        $this->artisanCommand('messages:telegram-webhook')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_registers_the_webhook_url_with_the_configured_secret(): void {
        $this->useCfg('telegram', ['bot-token' => 'bot-token', 'webhook-secret' => 'shh']);

        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->artisanCommand('messages:telegram-webhook')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('https://api.telegram.org/botbot-token/setWebhook', $request->url());
            $this->assertStringEndsWith('/api/telegram/webhook', strval($request['url']));
            $this->assertSame('shh', $request['secret_token']);

            return true;
        });
    }

    public function test_a_rejected_registration_fails(): void {
        $this->useCfg('telegram', ['bot-token' => 'bot-token', 'webhook-secret' => 'shh']);

        Http::fake(['*' => Http::response(['ok' => false, 'description' => 'bad webhook url'])]);

        $this->artisanCommand('messages:telegram-webhook')->assertFailed();
    }

}
