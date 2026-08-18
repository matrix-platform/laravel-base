<?php //>

namespace MatrixPlatform\Services\Messaging;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use MatrixPlatform\Jobs\SendMessageJob;
use MatrixPlatform\Messaging\Channel;
use MatrixPlatform\Messaging\Channels;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Support\Template;

abstract class MessageService {

    protected string $channel;

    public function __construct(private Channels $channels) {}

    public function cancel(int $reference): MessageLog {
        $this->record('cancel', ['reference' => $reference]);

        $log = $this->find($this->channels->get($this->channel), $reference);

        if ($log->status === MessageStatus::Scheduled) {
            $log->status = MessageStatus::Failed;
            $log->error = 'cancelled';

            $log->save();
        }

        return $log;
    }

    public function resend(int $reference, ?string $provider = null): MessageLog {
        $this->record('resend', ['reference' => $reference, 'provider' => $provider]);

        $channel = $this->channels->get($this->channel);
        $log = $this->find($channel, $reference)->replicate();

        if ($provider !== null) {
            $log->provider = $provider;
        }

        $log->schedule_time = now();
        $log->send_time = null;
        $log->response = null;
        $log->error = null;

        return $this->enqueue($channel, $log);
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, string|null> $options
     */
    public function schedule(DateTimeInterface $at, string $to, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        return $this->submit('schedule', Carbon::instance($at), $to, $template, $vars, $options);
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, string|null> $options
     */
    public function send(string $to, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        return $this->submit('send', now(), $to, $template, $vars, $options);
    }

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [];
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, string|null> $options
     * @return array<string, mixed>
     */
    private function compose(?string $template, array $vars, array $options): array {
        $fields = $template === null ? [] : Template::render($template, $vars);

        foreach (['subject', 'title', 'content', 'provider'] as $key) {
            if (isset($options[$key])) {
                $fields[$key] = $options[$key];
            }
        }

        if (strval(array_get_value($fields, 'content')) === '') {
            error('invalid-message-content');
        }

        return $fields;
    }

    private function enqueue(Channel $channel, MessageLog $log): MessageLog {
        $provider = $channel->provider($log->provider);
        $ready = !$log->schedule_time->isFuture() && $provider->driver() !== null;

        $log->status = $ready ? MessageStatus::Sending : MessageStatus::Scheduled;

        $log->save();

        if ($ready) {
            SendMessageJob::dispatchThrottled($provider, $log->id);
        }

        return $log;
    }

    private function find(Channel $channel, int $reference): MessageLog {
        return $channel->model::query()
            ->whereKey($reference)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(string $action, array $context): void {
        Log::info("messaging.{$this->channel}.{$action}", array_merge(['channel' => $this->channel, 'action' => $action], $context));
    }

    /**
     * @param array<string, mixed> $rendered
     */
    private function store(Carbon $at, string $to, ?string $template, array $rendered): MessageLog {
        $channel = $this->channels->get($this->channel);
        $model = $channel->model;
        $log = new $model();

        $provider = strval(array_get_value($rendered, 'provider'));

        $log->provider = $provider;
        $log->receiver = $to;
        $log->content = strval(array_get_value($rendered, 'content'));
        $log->template = $template;
        $log->schedule_time = $at;

        foreach ($this->attributes($rendered, $provider) as $key => $value) {
            $log->setAttribute($key, $value);
        }

        return $this->enqueue($channel, $log);
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, string|null> $options
     */
    private function submit(string $action, Carbon $at, string $to, ?string $template, array $vars, array $options): MessageLog {
        if ($to === '') {
            error('invalid-message-receiver');
        }

        $rendered = $this->compose($template, $vars, $options);

        $this->record($action, [
            'at' => $at->format('Y-m-d H:i:s'),
            'to' => $to,
            'template' => $template,
            'vars' => $vars,
            'options' => $options,
            'rendered' => $rendered
        ]);

        return $this->store($at, $to, $template, $rendered);
    }

}
