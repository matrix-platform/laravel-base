<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class DeleteService extends CrudService {

    /**
     * @var list<string>
     */
    private array $cascade = [];

    /**
     * @param list<string> $relations
     */
    public function cascade(array $relations): static {
        $this->cascade = $relations;

        return $this;
    }

    /**
     * @return array{id: list<mixed>}
     */
    public function delete(mixed $input): array {
        $values = is_array($input) ? $input : [];
        $items = array_values(array_unique(Arr::wrap(array_get_value($values, 'id'))));
        $models = $this->plain()
            ->whereIn("{$this->model->getTable()}.id", $items)
            ->get();

        if ($models->count() !== count($items)) {
            error('data-not-found', 404);
        }

        foreach ($models as $model) {
            $this->inspect($model);
        }

        foreach ($models as $model) {
            foreach ($this->cascade as $relation) {
                $this->purge($model, explode('.', $relation));
            }

            $model->delete();
        }

        return ['id' => $items];
    }

    /**
     * @param list<string> $chain
     */
    private function purge(Model $model, array $chain): void {
        $name = array_shift($chain);

        if ($name === null) {
            return;
        }

        foreach ($this->cascading($model, $name)->get() as $child) {
            $this->purge($child, $chain);

            $child->delete();
        }
    }

}
