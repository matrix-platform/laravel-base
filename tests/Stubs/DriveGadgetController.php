<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;

class DriveGadgetController extends CrudController {

    protected string $model = DriveGadget::class;

    protected bool $standalone = true;

}
