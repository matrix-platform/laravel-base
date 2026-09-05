<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Services\Admin\Crud\CrudService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\Operation;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Services\Admin\MfaService;
use MatrixPlatform\Services\Admin\PasswordService;
use MatrixPlatform\Support\AdminLevel;
use MatrixPlatform\Support\PermissionTree;

class UserController extends CrudController {

    protected string $model = User::class;

    protected array $readonly = ['confirmed_time'];

    protected array $sorting = [
        'username'
    ];

    /**
     * @return array{id: mixed}
     */
    #[Action('{id}/disable-mfa')]
    public function disableMfa(Request $request): array {
        $minimum = $this->minimumManageableId();
        $query = User::query();

        if ($minimum > User::ROOT) {
            $query->where('id', '>=', $minimum);
        }

        $model = $query->findOrFail($this->identifier($request));

        $this->guardNotSelf($model);

        app(MfaService::class)->disable($model);

        $model->writeLog(UserLogType::MfaDisabled);

        return ['id' => $model->getKey()];
    }

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
        return $this->visible(parent::onDelete($service))->guard(fn (Model $model) => $this->guardNotSelf($model));
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
        return $this->visible(parent::onList($service))->rowActions([
            'edit',
            'delete',
            new Operation('disable-mfa', fn (User $model): bool => $model->hasMfaEnabled() && $model->getKey() !== actor()->requireUser()->id)
        ]);
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return $this->visible(parent::onUpdate($service))->guard(app(PermissionTree::class)->filter());
    }

    private function guardNotSelf(Model $model): void {
        if ($model->getKey() === actor()->requireUser()->id) {
            error('permission-denied', 403);
        }
    }

    private function minimumManageableId(): int {
        return AdminLevel::of(actor()->requireUser()->id)->minimumManageableId();
    }

    /**
     * @template TService of CrudService
     * @param TService $service
     * @return TService
     */
    private function visible(CrudService $service): CrudService {
        $minimum = $this->minimumManageableId();

        return $service->when($minimum > User::ROOT, fn (Builder $query) => $query->where($query->getModel()->getQualifiedKeyName(), '>=', $minimum));
    }

}
