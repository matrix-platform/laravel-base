<?php //>

namespace Tests\Stubs;

use Illuminate\Http\Request;
use MatrixPlatform\Http\Controllers\Admin\GroupController;

class FailingGroupController extends GroupController {

    /**
     * @return array<string, mixed>
     */
    public function delete(Request $request): array {
        parent::delete($request);

        error('data-conflicted', 409);
    }

}
