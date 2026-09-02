<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\Operation;

class TrinketController extends CrudController {

    protected bool $exportable = true;

    protected ?array $lists = [
        'label',
        'widget.title',
        'amount'
    ];

    protected string $model = Trinket::class;

    protected ?bool $sortable = true;

    protected array $updates = [
        '*label',
        'amount',
        '!ranking'
    ];

    protected function onDelete(DeleteService $service): DeleteService {
        return parent::onDelete($service)->guard(fn (Trinket $trinket) => $trinket->label === 'locked' && error('permission-denied', 403));
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)
            ->pageActions(['new', 'sort', 'export', 'delete'])
            ->rowActions(['edit', 'copy', new Operation('delete', fn (Trinket $trinket) => $trinket->label !== 'locked')]);
    }

}
