<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MatrixPlatform\Support\MetadataRegistry;

class SortService extends CrudService {

    private string $ranking;

    public function __construct(string $model) {
        parent::__construct($model);

        $metadata = app(MetadataRegistry::class)->of($model);

        $this->ranking = $metadata !== null && $metadata->ranking !== null ? $metadata->ranking : 'ranking';
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
        $order = array_values(array_map(fn (mixed $id): string => strval($id), Arr::wrap(array_get_value($values, 'order'))));
        $models = $this->keyed($this->ordered());

        $given = $order;
        $expected = array_map(fn (int|string $key): string => strval($key), array_keys($models));

        sort($given);
        sort($expected);

        if ($given !== $expected) {
            error('invalid-sort-order');
        }

        $rankings = $this->reassignRankings($models, $this->ranking, $order);

        foreach ($order as $index => $id) {
            $model = $models[$id];

            $model->setAttribute($this->ranking, $rankings[$index]);

            $this->inspect($model);

            $model->save();
        }

        return ['id' => $order];
    }

    /**
     * @return Collection<int, Model>
     */
    private function ordered(): Collection {
        return $this->plain()
            ->orderBy($this->ranking)
            ->orderBy('id')
            ->get();
    }

}
