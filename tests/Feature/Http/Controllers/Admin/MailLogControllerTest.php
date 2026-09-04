<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Messaging\MailService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class MailLogControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->useMessagingFixtures();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function mail(): MailService {
        return app(MailService::class);
    }

    private function schedule(): MessageLog {
        return $this->mail()->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']);
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

        $response = $this->send('admin/mail-log');

        $response->assertJsonPath('data.rows.0.subject', 'Welcome Alice');
        $response->assertJsonPath('data.rows.0.receiver', 'alice@example.com');
        $response->assertJsonPath('data.rows.0.provider', 'stub');
    }

    public function test_resend_copies_the_log_and_queues_it(): void {
        $log = $this->schedule();

        $resentId = $this->send("admin/mail-log/{$log->id}/resend")->json('data.id');

        $this->assertNotSame($log->id, $resentId);
        $this->assertSame(MessageStatus::Scheduled, MailLog::query()->whereKey($resentId)->firstOrFail()->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'mail');
    }

    public function test_cancel_marks_a_scheduled_log_as_failed(): void {
        $log = $this->mail()->schedule(now()->addDay(), 'alice@example.com', 'welcome', ['name' => 'Alice']);

        $this->send("admin/mail-log/{$log->id}/cancel")->assertJsonPath('success', true);

        $this->assertSame(MessageStatus::Failed, MailLog::query()->whereKey($log->id)->firstOrFail()->status);
    }

    public function test_the_write_actions_are_unreachable_because_they_carry_no_menu_entry(): void {
        $this->send('admin/mail-log/insert')->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
