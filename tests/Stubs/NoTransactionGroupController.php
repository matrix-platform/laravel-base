<?php //>

namespace Tests\Stubs;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\Admin\GroupController;

class NoTransactionGroupController extends GroupController {

    /**
     * @return array<string, mixed>
     */
    #[Action(transaction: false)]
    public function delete(Request $request): array {
        parent::delete($request);

        error('data-conflicted', 409);
    }

}
