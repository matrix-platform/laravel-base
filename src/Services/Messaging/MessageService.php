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

    /**
     * @param array<string, string> $vars
     * @param array<string, string|null> $options
     */
    public function schedule(DateTimeInterface $at, string $to, ?string $template = null, array $vars = [], array $options = []): MessageLog {
        if ($to === '') {
            error('invalid-message-receiver');
        }

        $when = Carbon::instance($at);
        $rendered = $this->compose($template, $vars, $options);

        $this->record([
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
     * @param array<string, mixed> $context
     */
    private function record(array $context): void {
        Log::info("messaging.{$this->channel}.schedule", array_merge(['channel' => $this->channel, 'action' => 'schedule'], $context));
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
        $log->locale = app()->getLocale();

        foreach ($this->attributes($rendered, $provider) as $key => $value) {
            $log->setAttribute($key, $value);
        }

        return $this->enqueue($channel, $log);
    }

}
