<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\Menu;

class MenuChildrenController extends CrudController {

    protected array $counts = ['children'];

    protected string $model = Menu::class;

}
