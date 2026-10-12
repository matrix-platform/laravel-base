<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\CrudController;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\StagedContext;

class StagedGadgetController extends CrudController {

    /**
     * @var list<array{widget_id: mixed, context: mixed, parent: array<string, mixed>, staged: ?Model}>
     */
    public static array $inspected = [];

    protected string $model = StagedGadget::class;

    protected array $updates = [
        '*title',
        'attachments'
    ];

    protected function onInsert(InsertService $service): InsertService {
        return parent::onInsert($service)->guard($this->record(...));
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard($this->record(...));
    }

    /**
     * @param array<string, mixed>|Model|null $context
     */
    private function record(Model $model, array|Model|null $context): void {
        $staged = app(StagedContext::class);

        self::$inspected[] = ['widget_id' => $model->getAttribute('widget_id'), 'context' => $context, 'parent' => $staged->parent, 'staged' => $staged->model];

        if ($model->getAttribute('title') === 'refused') {
            invalid('title', 'refused');
        }
    }

}
