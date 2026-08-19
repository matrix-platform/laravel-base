<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\CommonService;

class CommonController extends BaseController {

    public function __construct(private CommonService $service) {}

    /**
     * @return list<array{id: int, title: string, areas: list<array{id: int, title: string, post_code: string}>}>
     */
    #[Action]
    public function city(): array {
        return $this->service->city();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Action]
    public function menu(Request $request): array {
        $request->validate([
            'parent' => ['nullable', 'integer']
        ]);

        return $this->service->menu($this->optional($request, 'parent'));
    }

    private function optional(Request $request, string $key): ?int {
        return $request->filled($key) ? $request->integer($key) : null;
    }

}
