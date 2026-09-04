<?php //>

namespace MatrixPlatform\Models;

use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\PushLogDeclaration;

/**
 * @property int $member_id
 * @property ?string $title
 * @property ?array<string, mixed> $data
 */
#[Declared(PushLogDeclaration::class)]
class PushLog extends MessageLog {

    protected $table = 'base_push_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            ...parent::casts(),
            'member_id' => 'integer',
            'data' => 'array'
        ];
    }

}
