<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Services\Admin\Crud\UpdateService;

class GuardedGroupController extends GroupController {

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard(function (Model $model): void {
            $model->setAttribute('title', strtoupper(strval($model->getAttribute('title'))));
        });
    }

}
