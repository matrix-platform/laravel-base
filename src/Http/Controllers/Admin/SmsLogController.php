<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Services\Messaging\SmsService;

class SmsLogController extends MessageLogController {

    protected ?array $lists = [
        'receiver',
        'provider',
        'status',
        'schedule_time',
        'send_time'
    ];

    protected string $model = SmsLog::class;

    public function __construct(SmsService $service) {
        parent::__construct($service);
    }

}
