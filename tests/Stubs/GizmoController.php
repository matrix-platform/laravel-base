<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;

class GizmoController extends CrudController {

    protected string $model = Gizmo::class;

    protected bool $standalone = true;

    protected array $updates = [
        'title'
    ];

}
