<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\Messaging\MessageService;
use MatrixPlatform\Support\MetadataRegistry;

abstract class MessageLogController extends CrudController {

    public function __construct(protected MessageService $service) {
        $definitions = app(MetadataRegistry::class)->definitions($this->model);

        $this->readonly = $definitions === null ? [] : array_keys($definitions);
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

}
