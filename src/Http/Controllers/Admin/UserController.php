<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Services\Admin\Crud\CrudService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Services\Admin\PasswordService;
use MatrixPlatform\Support\AdminLevel;
use MatrixPlatform\Support\PermissionTree;

class UserController extends CrudController {

    protected string $model = User::class;

    protected array $sorting = [
        'username'
    ];

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request): array {
        $result = parent::update($request);

        if ($request->filled('password')) {
            app(PasswordService::class)->revoke(User::query()->whereKey($result['id'])->firstOrFail());
        }

        return $result;
    }

    protected function onCopy(CopyService $service): CopyService {
        return $this->visible(parent::onCopy($service));
    }

    protected function onDelete(DeleteService $service): DeleteService {
        return $this->visible(parent::onDelete($service))->guard(function (Model $model): void {
            if ($model->getKey() === actor()->requireUser()->id) {
                error('permission-denied', 403);
            }
        });
    }

    protected function onExport(ExportService $service): ExportService {
        return $this->visible(parent::onExport($service));
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
        $minimum = AdminLevel::of(actor()->requireUser()->id)->minimumManageableId();

        return $service->when($minimum > User::ROOT, fn (Builder $query) => $query->where($query->getModel()->getQualifiedKeyName(), '>=', $minimum));
    }

}
