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
        $identity = actor()->requireCurrent();

        return $this->service->mask($identity, $this->service->get($identity));
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

        $identity = actor()->requireCurrent();

        return $this->service->mask($identity, $this->service->save($identity, $request->array('data'), $request->boolean('merge')));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function settings(): array {
        return $this->service->settings(actor()->requireCurrent());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('settings/update')]
    public function update(Request $request): array {
        return $this->service->update(actor()->requireCurrent(), $request->all());
    }

}
