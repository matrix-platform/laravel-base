<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Services\Messaging\TelegramService;

class TelegramLogController extends MessageLogController {

    protected ?array $lists = [
        'chat_id',
        'provider',
        'status',
        'schedule_time',
        'send_time'
    ];

    protected string $model = TelegramLog::class;

    public function __construct(TelegramService $service) {
        parent::__construct($service);
    }

}
