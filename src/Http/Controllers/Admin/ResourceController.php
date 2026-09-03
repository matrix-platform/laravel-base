<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\ResourceService;
use MatrixPlatform\Support\ResourceGroup;

abstract class ResourceController extends BaseController {

    protected ResourceGroup $group;

    public function __construct(private ResourceService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}')]
    public function get(Request $request): array {
        return $this->service->prefix($this->prefix())->get($this->group, $this->allowed($request));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('')]
    public function list(): array {
        return $this->service->prefix($this->prefix())->list($this->group, $this->unrestricted());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/update')]
    public function update(Request $request): array {
        return $this->service->prefix($this->prefix())->update($this->group, $this->allowed($request), $request->all());
    }

    private function allowed(Request $request): string {
        $name = strval($request->route('id'));

        if (!$this->unrestricted() && !$this->service->whitelisted($this->group, $name)) {
            error('data-not-found', 404);
        }

        return $name;
    }

    private function prefix(): string {
        return "resource/{$this->group->value}";
    }

    private function unrestricted(): bool {
        return user()?->id === User::ROOT;
    }

}
