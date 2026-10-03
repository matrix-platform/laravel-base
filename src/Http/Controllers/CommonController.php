<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\CommonService;

class CommonController extends BaseController {

    public function __construct(private CommonService $service) {}

    /**
     * @return array<string, mixed>|null
     */
    #[Action]
    public function cfg(Request $request): ?array {
        $request->validate([
            'name' => ['required', 'string']
        ]);

        return $this->service->cfg($request->string('name')->value());
    }

    /**
     * @return list<array{id: int, title: ?string, areas: list<array{id: int, title: ?string, post_code: string}>}>
     */
    #[Action]
    public function city(): array {
        return $this->service->city();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Action]
    public function i18n(Request $request): ?array {
        $request->validate([
            'name' => ['required', 'string']
        ]);

        return $this->service->i18n($request->string('name')->value());
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Action]
    public function menu(Request $request): array {
        $request->validate([
            'parent' => ['nullable', 'integer']
        ]);

        return $this->service->menu($this->optionalInteger($request, 'parent'));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function page(Request $request): array {
        $request->validate([
            'path' => ['required', 'string']
        ]);

        return $this->service->page($request->string('path')->value());
    }

}
