<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\Block;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Support\Variants;

class BlockController extends CrudController {

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $inserts = ['=type:text:block-module', '*title', 'data', 'enable_time', 'disable_time'];

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = [
        'title',
        '=type:text:block-module',
        ['name' => 'count(items)', 'path' => 'page/{page_id}/block/{id}/item']
    ];

    protected string $model = Block::class;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = ['!type:text:block-module', '*title', 'data', 'enable_time', 'disable_time'];

    protected function onDelete(DeleteService $service): DeleteService {
        return parent::onDelete($service)->cascade(['items']);
    }

    protected function onList(ListService $service): ListService {
        $variants = app(Variants::class);

        return parent::onList($service)->countable('items_count', fn (Block $block): bool => $variants->of('block-item-data', $block->type) !== null);
    }

}
