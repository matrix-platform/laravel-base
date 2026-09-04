<?php //>

namespace Tests\Feature\Messaging;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\TelegramDriver;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\TelegramSubscription;
use Tests\Factories\TelegramSubscriptionFactory;
use Tests\FeatureTestCase;

class TelegramDriverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMessagingFixtures();
    }

    private function log(string $identifier, string $provider = 'telegram'): TelegramLog {
        $log = new TelegramLog();

        $log->provider = $provider;
        $log->chat_id = $identifier;
        $log->content = 'Hello';
        $log->schedule_time = now();
        $log->status = MessageStatus::Scheduled;
        $log->locale = 'en';

        $log->save();

        return $log;
    }

    private function subscription(int $userId, string $chatId): TelegramSubscription {
        return TelegramSubscriptionFactory::new()->createOne(['user_id' => $userId, 'chat_id' => $chatId]);
    }

    public function test_a_user_id_is_resolved_through_the_subscription(): void {
        $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        (new TelegramDriver())->send($this->log('42'));

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '555666' && $request['text'] === 'Hello');
    }

    public function test_a_receiver_with_no_subscription_is_sent_as_a_literal_chat_id(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        (new TelegramDriver())->send($this->log('-1001234567890'));

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '-1001234567890');
    }

    public function test_extra_data_options_are_merged_into_the_request(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $log = $this->log('42');

        $log->data = ['parse_mode' => 'HTML'];

        (new TelegramDriver())->send($log);

        Http::assertSent(fn (Request $request) => $request['parse_mode'] === 'HTML');
    }

    public function test_a_blocked_bot_deletes_the_subscription_it_was_found_through(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('42')));

        $this->assertNull($subscription->fresh());
    }

    public function test_a_chat_not_found_response_deletes_the_subscription(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('42')));

        $this->assertNull($subscription->fresh());
    }

    public function test_a_group_chat_id_that_fails_has_no_subscription_to_delete(): void {
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('-1001234567890')));

        $this->assertSame(0, TelegramSubscription::query()->count());
    }

    public function test_the_error_description_is_recorded_on_the_log(): void {
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message text is empty'], 400)]);

        $log = $this->log('-1001234567890');

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($log));

        $this->assertSame('Bad Request: message text is empty', $log->error);
    }

    public function test_a_transport_level_failure_is_reported_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('not json', 500)]);

        $this->refuses('request-failed', fn () => (new TelegramDriver())->send($this->log('-1001234567890')));
    }

    public function test_a_sandboxed_provider_sends_to_the_sink_instead(): void {
        $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $response = (new TelegramDriver())->send($this->log('42', 'telegram-sandboxed'));

        $this->assertStringStartsWith('sandbox:', $response);

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '900000000');
    }

    public function test_a_sandboxed_failure_does_not_delete_the_real_subscription(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('42', 'telegram-sandboxed')));

        $this->assertNotNull($subscription->fresh());
    }

}
