<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\Concerns\StagesChildren;
use MatrixPlatform\Services\Admin\Crud\UpdateService;

class StagingWidgetController extends WidgetController {

    use StagesChildren;

    /**
     * @var array<string, string>
     */
    public static array $declared = [];

    /**
     * @var list<array{0: string, 1: string, 2?: int, 3?: int}>
     */
    public static array $hooks = [];

    public static ?string $refused = null;

    protected function afterStaged(string $key, Model $parent): void {
        self::$hooks[] = ['after', $key];
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return parent::onUpdate($service)->scope(fn (Builder $query) => $query->where('title', '!=', 'hidden'));
    }

    /**
     * @return array<string, string>
     */
    protected function staged(): array {
        return self::$declared;
    }

    protected function validateStaged(string $key, Model $parent): void {
        self::$hooks[] = ['validate', $key, Trinket::query()->where('widget_id', $parent->getKey())->count(), StagedGadget::query()->where('widget_id', $parent->getKey())->count()];

        if (self::$refused === $key) {
            invalid($key, 'refused');
        }
    }

}
