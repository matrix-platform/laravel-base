<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\Channel;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\MessageStatus;

class DispatchMessagesCommand extends Command {

    protected $description = 'Dispatch a sending job for every channel with messages waiting';

    protected $signature = 'messages:dispatch';

    public function handle(): int {
        $channels = app(Channels::class);
        $failed = false;

        foreach ($channels->names() as $name) {
            try {
                $channel = $channels->get($name);
            } catch (ServiceException $exception) {
                $this->error("{$name}: {$exception->getError()}");

                Log::error("messaging.{$name}.misconfigured", ['channel' => $name, 'code' => $exception->getError()]);

                $failed = true;

                continue;
            }

            if ($this->waiting($channel)) {
                SendMessageJob::dispatchFor($channel);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function waiting(Channel $channel): bool {
        return $channel->model::query()
            ->where('status', MessageStatus::Scheduled)
            ->where('schedule_time', '<=', now())
            ->exists();
    }

}
