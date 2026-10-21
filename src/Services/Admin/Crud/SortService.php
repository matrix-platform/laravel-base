<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Support\MetadataRegistry;

class SortService extends CrudService {

    private bool $descending = false;

    private string $ranking;

    public function __construct(string $model) {
        parent::__construct($model);

        $metadata = app(MetadataRegistry::class)->of($model);

        $this->ranking = $metadata?->ranking === null ? 'ranking' : $metadata->ranking;
    }

    public function descending(bool $descending): static {
        $this->descending = $descending;

        return $this;
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function items(): array {
        $rows = [];

        foreach ($this->ordered() as $model) {
            $rows[] = [
                'id' => $model->getKey(),
                'title' => $this->subject->title($model),
                'ranking' => $model->getAttribute($this->ranking)
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * @return array{id: list<string>}
     */
    public function sort(mixed $input): array {
        $values = is_array($input) ? $input : [];
        $raw = Arr::wrap(array_get_value($values, 'order'));

        if (!$this->scalars($raw)) {
            error('invalid-sort-order');
        }

        $order = array_values(array_map(strval(...), $raw));
        $models = $this->keyed($this->ordered());

        $given = $order;
        $expected = array_map(strval(...), array_keys($models));

        sort($given);
        sort($expected);

        if ($given !== $expected) {
            error('invalid-sort-order');
        }

        $ascending = $this->descending ? array_reverse($order) : $order;
        $rankings = $this->reassignRankings($models, $this->ranking, $ascending);

        foreach ($ascending as $index => $id) {
            $model = $models[$id];

            $model->setAttribute($this->ranking, $rankings[$index]);

            $this->inspect($model);

            $model->save();
        }

        return ['id' => $order];
    }

    /**
     * @return Collection<int, BaseModel>
     */
    private function ordered(): Collection {
        return $this->plain()
            ->orderBy($this->ranking, $this->descending ? 'desc' : 'asc')
            ->orderBy($this->model->getKeyName())
            ->get();
    }

}
