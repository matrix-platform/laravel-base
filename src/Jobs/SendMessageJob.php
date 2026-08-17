<?php //>

namespace MatrixPlatform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Messaging\Channel;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\Provider;
use MatrixPlatform\Models\MessageLog;
use Throwable;

class SendMessageJob implements ShouldQueue {

    use Dispatchable, Queueable;

    public static function dispatchThrottled(Provider $provider, int $id): void {
        static::dispatch($provider->channel, $id)->delay(self::nextDelay($provider));
    }

    private static function nextDelay(Provider $provider): int {
        $interval = (int) cfg("{$provider->bundle()}.interval", 0);

        if ($interval <= 0) {
            return 0;
        }

        $key = "messaging:{$provider->bundle()}:next-send";
        $now = now()->getTimestamp();
        $at = max((int) Cache::get($key, 0), $now);

        Cache::put($key, $at + $interval, $at + $interval - $now);

        return $at - $now;
    }

    public int $tries = 1;

    public function __construct(public string $channel, public int $id) {
        $this->onQueue(config()->string('matrix.messaging.queue'));
        $this->afterCommit();
    }

    public function handle(): void {
        $channel = app(Channels::class)->get($this->channel);
        $log = $this->claim($channel);

        if ($log === null) {
            return;
        }

        try {
            $driver = $channel->provider($log->provider)->driver();

            if ($driver === null) {
                error('message-provider-has-no-driver');
            }

            $log->response = $driver->send($log);
            $log->send_time = now();
            $log->status = MessageStatus::Success;
        } catch (Throwable $exception) {
            $log->status = MessageStatus::Failed;

            if ($log->error === null) {
                $log->error = $exception instanceof ServiceException ? $exception->getError() : $exception->getMessage();
            }
        }

        $log->save();
    }

    private function claim(Channel $channel): ?MessageLog {
        return DB::transaction(function () use ($channel): ?MessageLog {
            $log = $channel->model::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->first();

            return $log?->status === MessageStatus::Sending ? $log : null;
        });
    }

}
