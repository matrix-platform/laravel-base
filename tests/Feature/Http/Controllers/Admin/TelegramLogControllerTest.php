<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Messaging\TelegramService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class TelegramLogControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function telegram(): TelegramService {
        return app(TelegramService::class);
    }

    private function schedule(): MessageLog {
        return $this->telegram()->schedule(now(), '-1001234567890', null, [], ['provider' => 'telegram', 'content' => 'Hello']);
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_the_listing_reports_each_logs_fields(): void {
        $this->schedule();

        $response = $this->send('admin/telegram-log');

        $response->assertJsonPath('data.rows.0.chat_id', '-1001234567890');
        $response->assertJsonPath('data.rows.0.provider', 'telegram');
    }

    public function test_resend_copies_the_log_and_queues_it(): void {
        $log = $this->schedule();

        $resentId = $this->send("admin/telegram-log/{$log->id}/resend")->json('data.id');

        $this->assertNotSame($log->id, $resentId);
        $this->assertSame(MessageStatus::Scheduled, TelegramLog::query()->whereKey($resentId)->firstOrFail()->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'telegram');
    }

    public function test_cancel_marks_a_scheduled_log_as_failed(): void {
        $log = $this->telegram()->schedule(now()->addDay(), '-1001234567890', null, [], ['provider' => 'telegram', 'content' => 'Hello']);

        $this->send("admin/telegram-log/{$log->id}/cancel")->assertJsonPath('success', true);

        $this->assertSame(MessageStatus::Failed, TelegramLog::query()->whereKey($log->id)->firstOrFail()->status);
    }

    public function test_the_write_actions_are_unreachable_because_they_carry_no_menu_entry(): void {
        $this->send('admin/telegram-log/insert')->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
