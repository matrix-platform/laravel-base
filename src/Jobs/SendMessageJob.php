<?php //>

namespace MatrixPlatform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Messaging\Channel;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\Driver;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use Throwable;

class SendMessageJob implements ShouldQueue {

    use Dispatchable, Queueable;

    public static function dispatchFor(Channel $channel): void {
        static::dispatch($channel->name)->onQueue($channel->queue);
    }

    public function __construct(public string $channel) {
        $this->afterCommit();
    }

    public function handle(): void {
        $channel = app(Channels::class)->get($this->channel);
        $log = $this->next($channel);

        if ($log === null) {
            return;
        }

        $driver = $channel->provider($log->provider)->driver();

        if ($driver === null) {
            error('message-provider-has-no-driver');
        }

        $this->send($log, $driver);
        $this->pace($log->provider);

        self::dispatchFor($channel);
    }

    private function next(Channel $channel): ?MessageLog {
        return $channel->model::query()
            ->where('status', MessageStatus::Scheduled)
            ->where('schedule_time', '<=', now())
            ->orderBy('schedule_time')
            ->orderBy('id')
            ->first();
    }

    private function pace(string $provider): void {
        $interval = intval(cfg("{$provider}.interval", 0));

        if ($interval > 0) {
            Sleep::for($interval)->seconds();
        }
    }

    /**
     * @param Driver<MessageLog> $driver
     */
    private function send(MessageLog $log, Driver $driver): void {
        try {
            $log->response = $driver->send($log);
            $log->send_time = now();
            $log->status = MessageStatus::Success;
        } catch (Throwable $exception) {
            $log->status = MessageStatus::Failed;

            if ($log->error === null) {
                $log->error = $exception instanceof ServiceException ? $exception->getError() : $exception->getMessage();
            }

            Log::error("messaging.{$this->channel}.failed", [
                'channel' => $this->channel,
                'provider' => $log->provider,
                'id' => $log->id,
                'code' => $log->error
            ]);
        }

        $log->save();
    }

}
