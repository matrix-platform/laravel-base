<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;

class GadgetController extends CrudController {

    protected ?array $exports = [];

    protected string $model = Gadget::class;

    protected bool $standalone = true;

}
