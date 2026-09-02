<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;

class GadgetController extends CrudController {

    protected bool $exportable = true;

    protected ?array $exports = [];

    protected string $model = Gadget::class;

    protected bool $standalone = true;

}
