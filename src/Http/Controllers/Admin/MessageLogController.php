<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\Messaging\MessageService;

abstract class MessageLogController extends CrudController {

    public function __construct(protected MessageService $service) {}

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

}
