<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\UserController;

class ExportableUserController extends UserController {

    protected bool $exportable = true;

}
