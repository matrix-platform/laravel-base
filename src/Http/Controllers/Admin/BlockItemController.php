<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\BlockItem;

class BlockItemController extends CrudController {

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = ['title'];

    protected string $model = BlockItem::class;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = ['*title', 'data', 'enable_time', 'disable_time'];

}
