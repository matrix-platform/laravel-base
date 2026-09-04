<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Services\Messaging\PushService;

class PushLogController extends MessageLogController {

    protected ?array $lists = [
        'title',
        'member_id',
        'provider',
        'status',
        'schedule_time',
        'send_time'
    ];

    protected string $model = PushLog::class;

    public function __construct(PushService $service) {
        parent::__construct($service);
    }

}
