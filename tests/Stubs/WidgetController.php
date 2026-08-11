<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Http\Controllers\Admin\CrudController;

class WidgetController extends CrudController {

    protected ?array $lists = [
        'title',
        'count(trinkets)'
    ];

    protected string $model = Widget::class;

    protected array $sorting = ['-ranking'];

    protected bool $standalone = true;

    protected array $updates = [
        '*title',
        'secret',
        'enable_time'
    ];

}
