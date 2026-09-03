<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\Group;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\PermissionTree;

class GroupController extends CrudController {

    protected string $model = Group::class;

    protected array $sorting = [
        'title'
    ];

    protected function onInsert(InsertService $service): InsertService {
        return parent::onInsert($service)->guard(app(PermissionTree::class)->filter());
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard(app(PermissionTree::class)->filter());
    }

}
