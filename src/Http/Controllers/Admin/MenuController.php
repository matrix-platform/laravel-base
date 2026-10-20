<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Support\Subject;

class MenuController extends MenuChildrenController {

    protected bool $standalone = true;

    protected function onArrange(ArrangeService $service): ArrangeService {
        return parent::onArrange($service)->scope($this->roots());
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)->scope($this->roots());
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
