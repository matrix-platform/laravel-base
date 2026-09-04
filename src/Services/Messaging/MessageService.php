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

    public function cancel(int|string $reference): MessageLog {
        [, $log] = $this->locate($reference);

        $this->record('cancel', ['reference' => $reference]);

        if ($log->status === MessageStatus::Scheduled) {
            $log->status = MessageStatus::Failed;
            $log->error = 'cancelled';
            $log->save();
        }

        return $log;
    }

    public function resend(int|string $reference): MessageLog {
        [$channel, $log] = $this->locate($reference);

        $this->record('resend', ['reference' => $reference]);

        $copy = $log->replicate();
        $copy->send_time = null;
        $copy->response = null;
        $copy->error = null;
        $copy->schedule_time = now();

        return $this->enqueue($channel, $copy);
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, mixed> $options
     */
    public function schedule(DateTimeInterface $at, string $to, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        if ($to === '') {
            error('invalid-message-receiver');
        }

        $when = Carbon::instance($at);
        $rendered = $this->compose($template, $vars, $options);

        $this->record('schedule', [
            'at' => $when->format('Y-m-d H:i:s'),
            'to' => $to,
            'template' => $template,
            'vars' => $vars,
            'options' => $options,
            'rendered' => $rendered
        ]);

        return $this->store($when, $to, $template, $rendered);
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, mixed> $options
     */
    public function send(string $to, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        return $this->schedule(now(), $to, $template, $vars, $options);
    }

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [];
    }

    protected function receiverKey(): string {
        return 'receiver';
    }

    /**
     * @param array<string, string> $vars
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function compose(?string $template, array $vars, array $options): array {
        $fields = $template === null ? [] : Template::render($template, $vars);

        foreach (['subject', 'title', 'content', 'provider', 'data'] as $key) {
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
        if ($channel->provider($log->provider)->driver() === null) {
            error('message-provider-has-no-driver');
        }

        $log->status = MessageStatus::Scheduled;
        $log->save();

        if (!$log->schedule_time->isFuture()) {
            SendMessageJob::dispatchFor($channel);
        }

        return $log;
    }

    /**
     * @return array{0: Channel, 1: MessageLog}
     */
    private function locate(int|string $reference): array {
        $channel = $this->channels->get($this->channel);

        return [$channel, $channel->model::query()->whereKey($reference)->firstOrFail()];
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
        $log->setAttribute($this->receiverKey(), $to);
        $log->content = strval(array_get_value($rendered, 'content'));
        $log->template = $template;
        $log->schedule_time = $at;
        $log->locale = app()->getLocale();

        foreach ($this->attributes($rendered, $provider) as $key => $value) {
            $log->setAttribute($key, $value);
        }

        return $this->enqueue($channel, $log);
    }

}
