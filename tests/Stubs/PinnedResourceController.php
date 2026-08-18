<?php //>

namespace Tests\Stubs;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\ResourceService;
use MatrixPlatform\Support\ResourceGroup;

class PinnedResourceController extends BaseController {

    private const NAME = 'gmail';

    public function __construct(private ResourceService $service) {}

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function get(): array {
        return $this->service()->get(ResourceGroup::Cfg, self::NAME);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function update(Request $request): array {
        return $this->service()->update(ResourceGroup::Cfg, self::NAME, $request->input('data'));
    }

    private function service(): ResourceService {
        return $this->service->prefix('mail-setting');
    }

}
