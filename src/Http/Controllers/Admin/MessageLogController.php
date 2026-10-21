<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\Operation;
use MatrixPlatform\Services\Messaging\MessageService;
use MatrixPlatform\Support\MetadataRegistry;

abstract class MessageLogController extends CrudController {

    public function __construct(protected MessageService $service) {
        $this->readonly = array_keys(app(MetadataRegistry::class)->definitions($this->model) ?: []);
    }

    /**
     * @return array{id: mixed}
     */
    #[Action('{id}/cancel')]
    public function cancel(Request $request): array {
        return ['id' => $this->service->cancel($this->identifier($request))->id];
    }

    /**
     * @return array{id: mixed}
     */
    #[Action('{id}/resend')]
    public function resend(Request $request): array {
        return ['id' => $this->service->resend($this->identifier($request))->id];
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)->rowActions([
            'edit',
            new Operation('resend', fn (MessageLog $log): bool => $log->status !== MessageStatus::Scheduled),
            new Operation('cancel-message', fn (MessageLog $log): bool => $log->status === MessageStatus::Scheduled)
        ]);
    }

}
