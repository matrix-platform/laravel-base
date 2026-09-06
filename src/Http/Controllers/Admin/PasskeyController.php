<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\PasskeyCredential;
use MatrixPlatform\Services\Admin\Passkey\PasskeyService;

class PasskeyController extends BaseController {

    public function __construct(private PasskeyService $service) {}

    #[Action('{id}/delete')]
    public function destroy(Request $request): void {
        $this->service->delete(actor()->requireUser(), (int) $request->route('id'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Action('')]
    public function index(): array {
        return array_values($this->service->credentials(actor()->requireUser())
            ->map(fn (PasskeyCredential $credential): array => $this->present($credential))
            ->all());
    }

    /**
     * @return array{id: mixed}
     */
    #[Action('register')]
    public function register(Request $request): array {
        $request->validate([
            'challenge' => ['required'],
            'credential' => ['required', 'array'],
            'name' => ['required', 'string', 'max:100']
        ]);

        $credential = $this->service->register(
            actor()->requireUser(),
            $request->string('challenge')->value(),
            $request->array('credential'),
            $request->string('name')->value()
        );

        return ['id' => $credential->id];
    }

    /**
     * @return array{options: array<string, mixed>, challenge: string}
     */
    #[Action('register/options')]
    public function registrationOptions(): array {
        return $this->service->registrationOptions(actor()->requireUser());
    }

    #[Action('{id}/rename')]
    public function rename(Request $request): void {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $this->service->rename(actor()->requireUser(), (int) $request->route('id'), $request->string('name')->value());
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PasskeyCredential $credential): array {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'create_time' => $credential->create_time,
            'last_used_time' => $credential->last_used_time
        ];
    }

}
