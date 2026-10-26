<?php //>

namespace Tests\Feature\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\TelegramDriver;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\TelegramSubscription;
use MatrixPlatform\Support\CfgSchema;
use PHPUnit\Framework\Attributes\DataProvider;
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

        (new TelegramDriver())->send($this->log('user:42'));

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '555666' && $request['text'] === 'Hello');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function literalChatIds(): array {
        return [
            'group chat id' => ['-1001234567890'],
            'plain number matching a subscribed user id' => ['42'],
            'chat id beyond the user id range' => ['5123456789'],
            'channel username' => ['@mychannel']
        ];
    }

    #[DataProvider('literalChatIds')]
    public function test_a_receiver_without_the_user_prefix_is_sent_as_a_literal_chat_id(string $identifier): void {
        $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        (new TelegramDriver())->send($this->log($identifier));

        Http::assertSent(fn (Request $request) => $request['chat_id'] === $identifier);
    }

    public function test_a_user_without_a_subscription_is_refused_without_calling_telegram(): void {
        Http::fake();

        $this->refuses('telegram-not-subscribed', fn () => (new TelegramDriver())->send($this->log('user:42')));
        $this->refuses('telegram-not-subscribed', fn () => (new TelegramDriver())->send($this->log('user:abc')));

        Http::assertNothingSent();
    }

    public function test_the_data_payload_cannot_override_the_resolved_chat_id(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $log = $this->log('-1001234567890');

        $log->data = ['chat_id' => '-999999999', 'parse_mode' => 'HTML'];

        (new TelegramDriver())->send($log);

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '-1001234567890' && $request['parse_mode'] === 'HTML');
    }

    public function test_the_data_payload_cannot_override_the_recorded_text(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $log = $this->log('-1001234567890');

        $log->data = ['text' => 'something else entirely'];

        (new TelegramDriver())->send($log);

        Http::assertSent(fn (Request $request) => $request['text'] === 'Hello');
    }

    public function test_the_data_payload_cannot_escape_the_sandbox_sink(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $log = $this->log('42', 'telegram-sandboxed');

        $log->data = ['chat_id' => '-999999999'];

        $response = (new TelegramDriver())->send($log);

        $this->assertStringStartsWith('sandbox:', $response);

        Http::assertSent(fn (Request $request) => $request['chat_id'] !== '-999999999');
    }

    public function test_a_blocked_bot_deletes_the_subscription_it_was_found_through(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('user:42')));

        $this->assertNull($subscription->fresh());
    }

    public function test_a_chat_not_found_response_deletes_the_subscription(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('user:42')));

        $this->assertNull($subscription->fresh());
    }

    public function test_a_literal_chat_id_that_fails_does_not_delete_a_subscription_sharing_that_chat(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('555666')));

        $this->assertNotNull($subscription->fresh());
    }

    public function test_a_failure_that_is_not_an_expiry_keeps_the_subscription(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message text is empty'], 400)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('user:42')));

        $this->assertNotNull($subscription->fresh());
    }

    public function test_the_error_description_is_recorded_on_the_log(): void {
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message text is empty'], 400)]);

        $log = $this->log('-1001234567890');

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($log));

        $this->assertSame('Bad Request: message text is empty', $log->error);
    }

    public function test_a_refusal_without_a_description_leaves_the_error_to_the_caller(): void {
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 500], 500)]);

        $log = $this->log('-1001234567890');

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($log));

        $this->assertNull($log->error);
        $this->assertSame('{"ok":false,"error_code":500}', $log->response);
    }

    public function test_an_accepted_message_returns_and_records_the_whole_reply(): void {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]])]);

        $log = $this->log('-1001234567890');

        $this->assertSame('{"ok":true,"result":{"message_id":7}}', (new TelegramDriver())->send($log));
        $this->assertSame('{"ok":true,"result":{"message_id":7}}', $log->response);
    }

    public function test_a_transport_level_failure_is_reported_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('not json', 500)]);

        $this->refuses('request-failed', fn () => (new TelegramDriver())->send($this->log('-1001234567890')));
    }

    public function test_a_connection_failure_masks_the_bot_token_in_its_message(): void {
        $this->useCfg('telegram', ['bot-token' => '123456:SECRETTOKEN']);

        Http::fake(['*' => Http::failedConnection()]);

        try {
            (new TelegramDriver())->send($this->log('-1001234567890'));
        } catch (ConnectionException $exception) {
            $this->assertStringNotContainsString('SECRETTOKEN', $exception->getMessage());
            $this->assertStringContainsString('https://api.telegram.org/bot' . CfgSchema::MASK . '/sendMessage', $exception->getMessage());
            $this->assertStringContainsString('Could not resolve host', $exception->getMessage());
            $this->assertNull($exception->getPrevious());

            return;
        }

        $this->fail('the connection failure was expected to surface');
    }

    public function test_a_sandboxed_provider_sends_to_the_sink_instead(): void {
        $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $response = (new TelegramDriver())->send($this->log('user:42', 'telegram-sandboxed'));

        $this->assertStringStartsWith('sandbox:', $response);

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '900000000');
    }

    public function test_a_sandboxed_failure_does_not_delete_the_real_subscription(): void {
        $subscription = $this->subscription(42, '555666');

        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden'], 403)]);

        $this->refuses('message-refused-by-provider', fn () => (new TelegramDriver())->send($this->log('user:42', 'telegram-sandboxed')));

        $this->assertNotNull($subscription->fresh());
    }

    public function test_the_request_is_bounded_by_a_timeout(): void {
        $options = [];

        Http::fake(function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
        });

        (new TelegramDriver())->send($this->log('-1001234567890'));

        $this->assertSame([5, 20], [$options['connect_timeout'], $options['timeout']]);
    }

}
