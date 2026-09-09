<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\Shared\ScheduleService;

class ScheduleController extends BaseController {

    public function __construct(private ScheduleService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action('toggle')]
    public function toggle(Request $request): array {
        $request->validate([
            'prefix' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'enabled' => ['required', 'boolean']
        ]);

        return $this->service->toggle($request->string('prefix')->value(), $request->integer('id'), $request->boolean('enabled'));
    }

}
