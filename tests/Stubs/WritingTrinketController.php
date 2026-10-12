<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Http\Controllers\Admin\Concerns\WritesStagedRows;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Support\StagedContext;

class WritingTrinketController extends TrinketController implements WritesStagedRows {

    /**
     * @var list<array{0: string, 1?: string}>
     */
    public static array $calls = [];

    public function stagedAbilities(Model $parent): array {
        return ['insert' => true, 'update' => false, 'delete' => $parent->getAttribute('title') !== 'Locked'];
    }

    public function stagedDeleteRow(string $id): void {
        self::$calls[] = ['delete', $id];

        $trinket = $this->owned($id);

        $trinket->delete();
    }

    public function stagedInsertRow(array $row): Model {
        self::$calls[] = ['insert'];

        $trinket = new Trinket();

        $trinket->widget_id = intval(app(StagedContext::class)->model?->getKey());

        return $this->write($trinket, $row);
    }

    public function stagedPermissionTag(string $operation): string {
        return $operation === 'delete' ? 'update' : $operation;
    }

    public function stagedUpdateRow(string $id, array $row): Model {
        self::$calls[] = ['update', $id];

        return $this->write($this->owned($id), $row);
    }

    protected function onList(ListService $service): ListService {
        return parent::onList($service)->guard(function (Trinket $trinket): void {
            $trinket->setAttribute('label', strtoupper($trinket->label));
            $trinket->setAttribute('badge', "#{$trinket->id}");
        });
    }

    private function owned(string $id): Trinket {
        return Trinket::query()->where('widget_id', app(StagedContext::class)->model?->getKey())->findOrFail($id);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function write(Trinket $trinket, array $row): Trinket {
        $label = array_get_value($row, 'label');
        $label = is_string($label) ? $label : '';

        if ($label === 'sold out') {
            error('out-of-stock', 409, ['fields' => ['label' => ['out-of-stock']]]);
        }

        $trinket->label = $label;
        $trinket->amount = mb_strlen($label) * 100;
        $trinket->ranking = 0;

        $trinket->save();

        return $trinket;
    }

}
