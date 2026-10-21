<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Subject;

class MenuController extends MenuChildrenController {

    protected bool $standalone = true;

    protected function onArrange(ArrangeService $service): ArrangeService {
        return parent::onArrange($service)->scope($this->roots());
    }

    protected function onCopy(CopyService $service): CopyService {
        return parent::onCopy($service)->scope($this->roots());
    }

    protected function onDelete(DeleteService $service): DeleteService {
        return parent::onDelete($service)->scope($this->roots());
    }

    protected function onExport(ExportService $service): ExportService {
        return parent::onExport($service)->scope($this->roots());
    }

    protected function onGet(GetService $service): GetService {
        return parent::onGet($service)->scope($this->roots());
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)->scope($this->roots());
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->scope($this->roots());
    }

    private function roots(): Closure {
        return function (Builder $query): void {
            $foreign = app(Subject::class)->foreign($query->getModel());

            if ($foreign !== null) {
                $query->whereNull($query->qualifyColumn($foreign));
            }
        };
    }

}
