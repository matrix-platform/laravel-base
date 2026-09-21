<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Models\Page;
use MatrixPlatform\Services\Admin\Crud\DeleteService;

class PageController extends CrudController {

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = ['path', 'title', 'count(blocks)'];

    protected string $model = Page::class;

    protected function onDelete(DeleteService $service): DeleteService {
        return parent::onDelete($service)->cascade(['blocks.items']);
    }

}
