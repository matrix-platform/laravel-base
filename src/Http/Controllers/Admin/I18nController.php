<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\I18nService;

class I18nController extends BaseController {

    public function __construct(private I18nService $service) {}

    /**
     * @return array<string, mixed>|null
     */
    #[Action]
    public function get(Request $request): ?array {
        $request->validate([
            'name' => ['required', 'string']
        ]);

        return $this->service->get($request->string('name')->value());
    }

}
