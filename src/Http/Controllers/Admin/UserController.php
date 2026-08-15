<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\Crud\CrudService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\PermissionTree;

class UserController extends CrudController {

    protected ?array $lists = [
        'username',
        'group.title',
        'disabled',
        'enable_time',
        'disable_time'
    ];

    protected string $model = User::class;

    protected array $sorting = [
        'username'
    ];

    protected array $updates = [
        '*username',
        'password',
        'group_id',
        'disabled',
        'enable_time',
        'disable_time',
        ['name' => 'permissions', 'sortable' => false]
    ];

    protected function onDelete(DeleteService $service): DeleteService {
        return $this->visible(parent::onDelete($service))->guard(function (Model $model): void {
            if ($model->getKey() === actor()->requireUser()->id) {
                error('permission-denied', 403);
            }
        });
    }

    protected function onGet(GetService $service): GetService {
        return $this->visible(parent::onGet($service));
    }

    protected function onInsert(InsertService $service): InsertService {
        return parent::onInsert($service)->guard(app(PermissionTree::class)->filter());
    }

    protected function onList(ListService $service): ListService {
        return $this->visible(parent::onList($service));
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return $this->visible(parent::onUpdate($service))->guard(app(PermissionTree::class)->filter());
    }

    /**
     * @template TService of CrudService
     * @param TService $service
     * @return TService
     */
    private function visible(CrudService $service): CrudService {
        return $service->when(actor()->requireUser()->id !== User::ROOT, fn (Builder $query) => $query->whereKeyNot(User::ROOT));
    }

}
