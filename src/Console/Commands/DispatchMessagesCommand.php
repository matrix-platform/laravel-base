<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\Channel;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\Provider;

class DispatchMessagesCommand extends Command {

    protected $description = 'Dispatch due scheduled messages to the queue';

    protected $signature = 'messages:dispatch';

    public function handle(): void {
        $channels = app(Channels::class);

        foreach ($channels->names() as $name) {
            try {
                $channel = $channels->get($name);
            } catch (ServiceException $exception) {
                $this->error("{$name}: {$exception->getError()}");

                continue;
            }

            $this->dispatchDue($channel);
        }
    }

    private function deliverable(Channel $channel, string $name): ?Provider {
        try {
            $provider = $channel->provider($name);

            return $provider->driver() === null ? null : $provider;
        } catch (ServiceException $exception) {
            $this->error("{$channel->name}/{$name}: {$exception->getError()}");

            return null;
        }
    }

    private function dispatchDue(Channel $channel): void {
        $due = $channel->model::query()
            ->where('status', MessageStatus::Scheduled)
            ->where('schedule_time', '<=', now())
            ->orderBy('id')
            ->pluck('provider', 'id');

        foreach ($due as $id => $name) {
            $provider = $this->deliverable($channel, strval($name));

            if ($provider !== null) {
                $this->dispatchLog($channel, $provider, intval($id));
            }
        }
    }

    private function dispatchLog(Channel $channel, Provider $provider, int $id): void {
        DB::transaction(function () use ($channel, $provider, $id): void {
            $log = $channel->model::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if ($log?->status !== MessageStatus::Scheduled) {
                return;
            }

            $log->status = MessageStatus::Sending;

            $log->save();

            SendMessageJob::dispatchThrottled($provider, $id);
        });
    }

}
