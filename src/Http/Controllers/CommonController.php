<?php //>

namespace MatrixPlatform\Http\Controllers;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Services\CommonService;

class CommonController extends BaseController {

    public function __construct(private CommonService $service) {}

    #[Action]
    public function city() {
        return $this->service
            ->city()
            ->map(fn ($city) => [
                'id' => $city->id,
                'title' => $city->title,
                'areas' => $city->areas->map->only(['id', 'title', 'post_code'])
            ]);
    }

    #[Action]
    public function menu(Request $request) {
        return $this->service
            ->menu($request->input('parent_id'))
            ->map->only(['id', 'parent_id', 'title', 'data']);
    }

}
