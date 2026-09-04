<?php //>

namespace Tests\Feature\Services\Messaging;

use Illuminate\Support\Facades\Queue;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Services\Messaging\TelegramService;
use Tests\FeatureTestCase;

class TelegramServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Queue::fake();
    }

    private function telegram(): TelegramService {
        return app(TelegramService::class);
    }

    private function reload(MessageLog $log): TelegramLog {
        return TelegramLog::query()
            ->whereKey($log->id)
            ->firstOrFail();
    }

    public function test_the_data_option_is_stored_on_the_log(): void {
        $log = $this->telegram()->schedule(now(), '42', null, [], ['provider' => 'telegram', 'content' => 'Hello', 'data' => ['parse_mode' => 'HTML']]);
        $telegram = $this->reload($log);

        $this->assertSame('42', $telegram->chat_id);
        $this->assertSame('Hello', $telegram->content);
        $this->assertSame(['parse_mode' => 'HTML'], $telegram->data);
        $this->assertSame(MessageStatus::Scheduled, $telegram->status);

        Queue::assertPushed(SendMessageJob::class, fn (SendMessageJob $job) => $job->channel === 'telegram');
    }

    public function test_a_message_without_data_stores_a_null_data(): void {
        $log = $this->telegram()->schedule(now(), '42', null, [], ['provider' => 'telegram', 'content' => 'Hello']);

        $this->assertNull($this->reload($log)->data);
    }

    public function test_a_group_chat_id_is_accepted_as_the_destination(): void {
        $log = $this->telegram()->schedule(now(), '-1001234567890', null, [], ['provider' => 'telegram', 'content' => 'Hello']);

        $this->assertSame('-1001234567890', $this->reload($log)->chat_id);
    }

}
