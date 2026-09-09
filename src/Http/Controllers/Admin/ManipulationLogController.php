<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\Shared\ManipulationLogService;

class ManipulationLogController extends BaseController {

    public function __construct(private ManipulationLogService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action('query')]
    public function query(Request $request): array {
        $request->validate([
            'prefix' => ['required', 'string'],
            'id' => ['required', 'integer']
        ]);

        return $this->service->query($request->string('prefix')->value(), $request->integer('id'), max(1, $request->integer('page', 1)), min(100, max(1, $request->integer('size', 10))));
    }

}
