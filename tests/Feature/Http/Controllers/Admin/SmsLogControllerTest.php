<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Messaging\SmsService;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class SmsLogControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();

        $this->useMessagingFixtures();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    private function schedule(): MessageLog {
        return $this->sms()->schedule(now(), '0912345678', 'otp', ['code' => '123456']);
    }

    private function sms(): SmsService {
        return app(SmsService::class);
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

        $response = $this->send('admin/sms-log');

        $response->assertJsonPath('data.rows.0.receiver', '0912345678');
        $response->assertJsonPath('data.rows.0.provider', 'twilio');
    }

    public function test_resend_copies_the_log_and_queues_it(): void {
        $log = $this->schedule();

        $resentId = $this->send("admin/sms-log/{$log->id}/resend")->json('data.id');

        $this->assertNotSame($log->id, $resentId);
        $this->assertSame(MessageStatus::Scheduled, SmsLog::query()->whereKey($resentId)->firstOrFail()->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'sms');
    }

    public function test_cancel_marks_a_scheduled_log_as_failed(): void {
        $log = $this->sms()->schedule(now()->addDay(), '0912345678', 'otp', ['code' => '123456']);

        $this->send("admin/sms-log/{$log->id}/cancel")->assertJsonPath('success', true);

        $this->assertSame(MessageStatus::Failed, SmsLog::query()->whereKey($log->id)->firstOrFail()->status);
    }

    public function test_the_write_actions_are_unreachable_because_they_carry_no_menu_entry(): void {
        $this->send('admin/sms-log/insert')->assertJson(['success' => false, 'code' => 403, 'error' => 'permission-denied']);
    }

}
