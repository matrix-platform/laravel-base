<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Services\Messaging\MailService;

class MailLogController extends MessageLogController {

    protected ?array $lists = [
        'subject',
        'receiver',
        'provider',
        'status',
        'schedule_time',
        'send_time'
    ];

    protected string $model = MailLog::class;

    public function __construct(MailService $service) {
        parent::__construct($service);
    }

}
