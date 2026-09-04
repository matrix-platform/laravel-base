<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\TelegramLogDeclaration;

/**
 * @property string $chat_id
 * @property ?array<string, mixed> $data
 */
#[Declared(TelegramLogDeclaration::class)]
class TelegramLog extends MessageLog {

    protected $table = 'base_telegram_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            ...parent::casts(),
            'data' => 'array'
        ];
    }

}
