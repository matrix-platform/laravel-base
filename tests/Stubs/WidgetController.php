<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\CrudController;
use MatrixPlatform\Services\Admin\Crud\CopyService;

class WidgetController extends CrudController {

    protected ?array $lists = [
        'title',
        'count(trinkets)'
    ];

    protected string $model = Widget::class;

    protected bool $sortable = true;

    protected array $sorting = ['-ranking'];

    protected bool $standalone = true;

    protected array $updates = [
        '*title',
        'secret',
        'enable_time'
    ];

    protected function onCopy(CopyService $service): CopyService {
        return parent::onCopy($service)
            ->cascade(['trinkets'])
            ->guard(function (Model $model): void {
                if ($model instanceof Trinket && $model->label === 'locked') {
                    error('permission-denied', 403);
                }
            });
    }

}
