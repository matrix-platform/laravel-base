<?php //>

namespace Tests\Feature\Services\Messaging;

use Illuminate\Support\Facades\Queue;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Services\Messaging\PushService;
use Tests\FeatureTestCase;

class PushServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();
    }

    private function push(): PushService {
        return app(PushService::class);
    }

    private function reload(MessageLog $log): PushLog {
        return PushLog::query()
            ->whereKey($log->id)
            ->firstOrFail();
    }

    public function test_the_title_and_data_options_are_stored_on_the_log(): void {
        $log = $this->push()->schedule(now(), '1', null, [], ['provider' => 'webpush', 'title' => 'Notice', 'content' => 'Body', 'data' => ['order_id' => 42]]);
        $push = $this->reload($log);

        $this->assertSame(1, $push->member_id);
        $this->assertSame('Notice', $push->title);
        $this->assertSame('Body', $push->content);
        $this->assertSame(['order_id' => 42], $push->data);
        $this->assertSame(MessageStatus::Scheduled, $push->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'push');
    }

    public function test_a_message_without_a_title_stores_a_null_title(): void {
        $log = $this->push()->schedule(now(), '1', null, [], ['provider' => 'webpush', 'content' => 'Body']);

        $this->assertNull($this->reload($log)->title);
    }

    public function test_a_message_without_data_stores_a_null_data(): void {
        $log = $this->push()->schedule(now(), '1', null, [], ['provider' => 'webpush', 'content' => 'Body']);

        $this->assertNull($this->reload($log)->data);
    }

}
