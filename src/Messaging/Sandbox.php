<?php //>

namespace MatrixPlatform\Messaging;

class Sandbox {

    public static function recipient(string $bundle): ?string {
        if (!cfg("{$bundle}.sandbox")) {
            return null;
        }

        $recipient = strval(cfg("{$bundle}.sandbox-recipient"));

        if ($recipient === '') {
            error('invalid-message-receiver');
        }

        return $recipient;
    }

    public static function response(?string $recipient, string $id): string {
        return $recipient === null ? $id : trim("sandbox: {$recipient} {$id}");
    }

}
