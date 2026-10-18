<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MatrixPlatform\Columns\Declarations\Presets;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\Operation;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\BlockDataGuard;
use MatrixPlatform\Support\Variants;

class BlockController extends CrudController {

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $inserts = ['=type:text:block-module', '*title', 'data', 'enable_time', 'disable_time'];

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = ['=type:text:block-module', 'title', 'count(items)', 'update_time'];

    protected string $model = Block::class;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = ['!type:text:block-module', '*title', 'data', 'enable_time', 'disable_time'];

    /**
     * @return array<string, mixed>
     */
    public function new(Request $request): array {
        $variant = app(Variants::class)->variant('block-data', null, $request->all());
        $defaults = $variant instanceof Presets ? $variant->defaults() : [];

        $request->mergeIfMissing(Arr::prependKeysWith($defaults, 'data__'));

        return parent::new($request);
    }

    protected function onCopy(CopyService $service): CopyService {
        return parent::onCopy($service)->cascade(['items'])->guard($this->lock(...));
    }

    protected function onDelete(DeleteService $service): DeleteService {
        return parent::onDelete($service)->cascade(['items'])->guard($this->lock(...));
    }

    protected function onInsert(InsertService $service): InsertService {
        $types = array_map(fn (Option $option): string => strval($option->id), (new BundleOptions('block-module'))->options());
        $types = array_diff($types, [Block::PAGE_CONTENT]);

        return parent::onInsert($service->columns([['name' => '=type:text:block-module', 'rule' => ['string', 'in:' . implode(',', $types)]]]))
            ->guard($this->inspect(...));
    }

    protected function onList(ListService $service): ListService {
        $variants = app(Variants::class);
        $unlocked = fn (Block $block): bool => $block->type !== Block::PAGE_CONTENT;

        return parent::onList($service->columns([['name' => '=type:text:block-module', 'options' => $this->listedTypes()]]))
            ->countable('items_count', fn (Block $block): bool => $variants->of('block-item-data', $block->type) !== null)
            ->rowActions(['edit', new Operation('copy', $unlocked), new Operation('delete', $unlocked)]);
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->guard($this->inspect(...));
    }

    private function inspect(Block $block): void {
        BlockDataGuard::inspect(app(Variants::class)->of('block-data', $block->type), $block->data);
    }

    private function listedTypes(): StaticOptions {
        $options = array_map(
            fn (Option $option): Option => $option->id === Block::PAGE_CONTENT ? new Option($option->children, $option->id, $option->ranking, $option->title, $option->deleted, false) : $option,
            (new BundleOptions('block-module'))->options()
        );

        return new StaticOptions($options);
    }

    private function lock(Model $model): void {
        if ($model instanceof Block && $model->type === Block::PAGE_CONTENT) {
            error('page-content-locked');
        }
    }

}
