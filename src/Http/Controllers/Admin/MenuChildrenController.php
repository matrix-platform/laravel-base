<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\Operation;
use MatrixPlatform\Support\MenuLocks;
use MatrixPlatform\Support\MetadataRegistry;

class MenuChildrenController extends CrudController {

    protected array $counts = ['children'];

    protected string $model = Menu::class;

    /**
     * @var list<string>
     */
    protected array $selects = ['data'];

    /**
     * @return array<string, mixed>
     */
    public function get(Request $request): array {
        $menu = Menu::query()->find($this->identifier($request));

        if ($menu instanceof Menu && MenuLocks::synced($menu)) {
            $this->readonly = array_keys(app(MetadataRegistry::class)->definitions(Menu::class) ?: []);
        }

        return parent::get($request);
    }

    protected function onArrange(ArrangeService $service): ArrangeService {
        return parent::onArrange($service)->fixed(fn (Menu $menu): bool => MenuLocks::synced($menu));
    }

    protected function onGet(GetService $service): GetService {
        return parent::onGet($service)->actions(['cancel', new Operation('update', fn (Menu $menu): bool => !MenuLocks::synced($menu))]);
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)
            ->countable('children_count', fn (Menu $menu): bool => !MenuLocks::leaf($menu))
            ->fixed(fn (Menu $menu): bool => MenuLocks::synced($menu))
            ->rowActions(['edit', 'copy', new Operation('delete', fn (Menu $menu): bool => !MenuLocks::locked($menu))]);
    }

}
