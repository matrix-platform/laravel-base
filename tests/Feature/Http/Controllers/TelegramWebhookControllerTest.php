<?php //>

namespace Tests\Feature\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MatrixPlatform\Models\TelegramSubscription;
use Tests\Factories\TelegramSubscriptionFactory;
use Tests\FeatureTestCase;

class TelegramWebhookControllerTest extends FeatureTestCase {

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function update(array $overrides = []): array {
        return array_replace_recursive([
            'update_id' => 1,
            'message' => [
                'text' => '/start bogus-token',
                'chat' => ['id' => 555666],
                'from' => ['username' => 'alice']
            ]
        ], $overrides);
    }

    public function test_a_request_without_the_secret_header_is_refused(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $this->postJson('api/telegram/webhook', $this->update())
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_a_request_with_the_wrong_secret_is_refused(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong')
            ->postJson('api/telegram/webhook', $this->update())
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_an_unconfigured_secret_refuses_every_request(): void {
        $this->postJson('api/telegram/webhook', $this->update())
            ->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

    public function test_a_valid_start_command_binds_the_subscription(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        Cache::put('telegram-link:abc123', 5, 600);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start abc123']]))
            ->assertJson(['success' => true]);

        $subscription = TelegramSubscription::query()->where('user_id', 5)->firstOrFail();

        $this->assertSame('555666', $subscription->chat_id);
        $this->assertSame('alice', $subscription->username);
    }

    public function test_the_token_is_consumed_and_cannot_be_reused(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        Cache::put('telegram-link:abc123', 5, 600);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start abc123']]));

        $this->assertNull(Cache::get('telegram-link:abc123'));
    }

    public function test_an_unknown_token_is_ignored_without_error(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start does-not-exist']]))
            ->assertJson(['success' => true]);

        $this->assertSame(0, TelegramSubscription::query()->count());
    }

    public function test_a_message_that_is_not_a_start_command_is_ignored(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => 'hello there']]))
            ->assertJson(['success' => true]);

        $this->assertSame(0, TelegramSubscription::query()->count());
    }

    public function test_rebinding_the_same_user_updates_the_existing_subscription(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $subscription = TelegramSubscriptionFactory::new()->createOne(['user_id' => 5, 'chat_id' => '111111']);

        Cache::put('telegram-link:abc123', 5, 600);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start abc123']]));

        $this->assertSame(1, TelegramSubscription::query()->count());
        $this->assertSame('555666', $subscription->fresh()?->chat_id);
    }

    public function test_binding_a_chat_id_already_claimed_by_another_user_is_refused(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $existing = TelegramSubscriptionFactory::new()->createOne(['user_id' => 5, 'chat_id' => '555666']);

        Cache::put('telegram-link:abc123', 6, 600);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start abc123']]))
            ->assertJson(['success' => true]);

        $this->assertSame(1, TelegramSubscription::query()->count());
        $this->assertSame(5, $existing->fresh()?->user_id);
    }

    public function test_a_bind_conflict_is_written_to_the_application_log(): void {
        $this->useCfg('telegram', ['webhook-secret' => 'shh']);

        $existing = TelegramSubscriptionFactory::new()->createOne(['user_id' => 5, 'chat_id' => '555666']);

        Cache::put('telegram-link:abc123', 6, 600);

        $spy = Log::spy();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh')
            ->postJson('api/telegram/webhook', $this->update(['message' => ['text' => '/start abc123']]));

        $spy->shouldHaveReceived('error', ['messaging.telegram.bind-conflict', ['user_id' => 6, 'chat_id' => '555666']]);
    }

}
