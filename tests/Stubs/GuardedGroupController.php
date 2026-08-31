<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Services\Admin\Crud\UpdateService;

class GuardedGroupController extends GroupController {

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard(function (Model $model): void {
            $model->setAttribute('title__tw', strtoupper(strval($model->getAttribute('title__tw'))));
            $model->setAttribute('title__en', strtoupper(strval($model->getAttribute('title__en'))));
        });
    }

}
