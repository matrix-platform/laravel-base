<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\Generators\CreatorAddress;

/**
 * @property int $id
 * @property string $provider
 * @property string $receiver
 * @property string $content
 * @property ?string $template
 * @property Carbon $schedule_time
 * @property ?Carbon $send_time
 * @property ?string $response
 * @property ?string $error
 * @property ?string $ip
 * @property string $locale
 * @property MessageStatus $status
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
abstract class MessageLog extends BaseModel {

    const TRACEABLE = false;

    protected array $generators = [
        'ip' => CreatorAddress::class
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'schedule_time' => 'datetime',
            'send_time' => 'datetime',
            'status' => MessageStatus::class
        ];
    }

}
