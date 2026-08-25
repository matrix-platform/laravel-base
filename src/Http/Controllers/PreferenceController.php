<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\PreferenceService;

class PreferenceController extends BaseController {

    public function __construct(private PreferenceService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function get(): array {
        return $this->service->get(actor()->requireCurrent());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function save(Request $request): array {
        $request->validate([
            'data' => ['required', 'array'],
            'merge' => ['sometimes', 'boolean']
        ]);

        return $this->service->save(actor()->requireCurrent(), $request->array('data'), $request->boolean('merge'));
    }

}
