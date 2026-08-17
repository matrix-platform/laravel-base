<?php //>

namespace MatrixPlatform\Services\Messaging;

class MailService extends MessageService {

    protected string $channel = 'mail';

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [
            'sender' => strval(cfg("{$this->channel}/{$provider}.from-address")),
            'subject' => strval(array_get_value($rendered, 'subject'))
        ];
    }

}
