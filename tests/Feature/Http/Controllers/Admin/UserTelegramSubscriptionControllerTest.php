<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Support\Facades\Cache;
use MatrixPlatform\Models\TelegramSubscription;
use Tests\Factories\TelegramSubscriptionFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class UserTelegramSubscriptionControllerTest extends FeatureTestCase {

    private function token(int $id = 1): string {
        return UserFactory::new()->createOne(['id' => $id])->createToken();
    }

    public function test_link_without_a_token_is_refused(): void {
        $this->postJson('admin/user/telegram/link')->assertJson(['success' => false, 'code' => 401, 'error' => 'invalid-token']);
    }

    public function test_link_returns_a_deep_link_containing_a_fresh_token(): void {
        $this->useCfg('telegram', ['bot-username' => 'my_bot']);

        $url = strval($this->withToken($this->token())->postJson('admin/user/telegram/link')->json('data.url'));

        $this->assertStringStartsWith('https://t.me/my_bot?start=', $url);
    }

    public function test_the_generated_token_resolves_back_to_the_current_user(): void {
        $url = strval($this->withToken($this->token(5))->postJson('admin/user/telegram/link')->json('data.url'));
        $token = substr($url, strrpos($url, '=') + 1);

        $this->assertSame(5, Cache::get("telegram-link:{$token}"));
    }

    public function test_unsubscribe_removes_the_users_subscription(): void {
        TelegramSubscriptionFactory::new()->createOne(['user_id' => 5, 'chat_id' => '555666']);

        $this->withToken($this->token(5))
            ->postJson('admin/user/telegram/unsubscribe')
            ->assertJson(['success' => true]);

        $this->assertSame(0, TelegramSubscription::query()->count());
    }

    public function test_unsubscribe_does_not_remove_another_users_subscription(): void {
        TelegramSubscriptionFactory::new()->createOne(['user_id' => 5, 'chat_id' => '555666']);

        $this->withToken($this->token(6))->postJson('admin/user/telegram/unsubscribe');

        $this->assertSame(1, TelegramSubscription::query()->count());
    }

}
