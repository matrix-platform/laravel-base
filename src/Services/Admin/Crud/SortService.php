<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SortService extends CrudService {

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function items(): array {
        $rows = [];

        foreach ($this->ordered() as $model) {
            $rows[] = [
                'id' => $model->getKey(),
                'title' => $this->subject->title($model),
                'ranking' => $model->getAttribute('ranking')
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
        $models = [];

        foreach ($this->ordered() as $model) {
            $models[strval($model->getKey())] = $model;
        }

        $given = $order;
        $expected = array_map(fn (int|string $key): string => strval($key), array_keys($models));

        sort($given);
        sort($expected);

        if ($given !== $expected) {
            error('invalid-sort-order');
        }

        $rankings = Ranking::reassign(array_map(fn (string $id): int => intval($models[$id]->getAttribute('ranking')), $order));

        foreach ($order as $index => $id) {
            $model = $models[$id];

            $model->setAttribute('ranking', $rankings[$index]);

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
            ->orderBy('ranking')
            ->orderBy('id')
            ->get();
    }

}
