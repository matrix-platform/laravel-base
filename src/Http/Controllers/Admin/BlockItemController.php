<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\BlockDataGuard;
use MatrixPlatform\Support\Variants;
use Symfony\Component\HttpFoundation\Response;

class BlockItemController extends CrudController {

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = ['title'];

    protected string $model = BlockItem::class;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = ['*title', 'data'];

    private ?Variant $variant = null;

    /**
     * @param array<string, mixed> $parameters
     */
    public function callAction($method, $parameters): Response {
        $this->variant = app(Variants::class)->of('block-item-data', Block::query()->whereKey(request()->route('block_id'))->first()?->type);

        if ($this->variant === null) {
            error('data-not-found', 404);
        }

        return parent::callAction($method, $parameters);
    }

    protected function onInsert(InsertService $service): InsertService {
        return parent::onInsert($service)->guard($this->inspect(...));
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard($this->inspect(...));
    }

    private function inspect(BlockItem $item): void {
        BlockDataGuard::inspect($this->variant, $item->data);
    }

}
