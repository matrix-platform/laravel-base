<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Messaging\PushService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class PushLogControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function push(): PushService {
        return app(PushService::class);
    }

    private function schedule(): MessageLog {
        return $this->push()->schedule(now(), '1', null, [], ['provider' => 'webpush', 'title' => 'Notice', 'content' => 'Body']);
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

        $response = $this->send('admin/push-log');

        $response->assertJsonPath('data.rows.0.title', 'Notice');
        $response->assertJsonPath('data.rows.0.member_id', 1);
        $response->assertJsonPath('data.rows.0.provider', 'webpush');
    }

    public function test_resend_copies_the_log_and_queues_it(): void {
        $log = $this->schedule();

        $resentId = $this->send("admin/push-log/{$log->id}/resend")->json('data.id');

        $this->assertNotSame($log->id, $resentId);
        $this->assertSame(MessageStatus::Scheduled, PushLog::query()->whereKey($resentId)->firstOrFail()->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'push');
    }

    public function test_cancel_marks_a_scheduled_log_as_failed(): void {
        $log = $this->push()->schedule(now()->addDay(), '1', null, [], ['provider' => 'webpush', 'title' => 'Notice', 'content' => 'Body']);

        $this->send("admin/push-log/{$log->id}/cancel")->assertJsonPath('success', true);

        $this->assertSame(MessageStatus::Failed, PushLog::query()->whereKey($log->id)->firstOrFail()->status);
    }

    public function test_the_write_actions_are_unreachable_because_they_carry_no_menu_entry(): void {
        $this->send('admin/push-log/insert')->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
