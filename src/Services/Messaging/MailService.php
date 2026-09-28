<?php //>

namespace MatrixPlatform\Services\Messaging;

class MailService extends MessageService {

    private static function addresses(mixed $value): ?string {
        $addresses = array_unique(tokenize(is_array($value) ? implode(' ', $value) : strval($value)));

        return $addresses === [] ? null : implode(', ', $addresses);
    }

    protected string $channel = 'mail';

    /**
     * @param array<string, mixed> $rendered
     * @return array<string, mixed>
     */
    protected function attributes(array $rendered, string $provider): array {
        return [
            'sender' => strval(cfg("{$provider}.from-address")),
            'cc' => self::addresses(array_get_value($rendered, 'cc')),
            'bcc' => self::addresses(array_get_value($rendered, 'bcc')),
            'subject' => strval(array_get_value($rendered, 'subject'))
        ];
    }

}
