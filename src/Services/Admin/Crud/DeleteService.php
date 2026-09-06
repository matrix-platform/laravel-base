<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

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

        $chains = array_map(fn (string $relation): array => explode('.', $relation), $this->cascade);
        $excluded = array_column($chains, 0);

        foreach ($models as $model) {
            $this->inspect($model);
            $this->guardReferences($model, $excluded);
        }

        foreach ($models as $model) {
            foreach ($chains as $chain) {
                $this->purge($model, $chain);
            }

            $model->delete();
        }

        return ['id' => $items];
    }

    /**
     * @param list<string> $excluded
     */
    private function guardReferences(Model $model, array $excluded): void {
        foreach (array_diff($this->referencingRelations($model), $excluded) as $relation) {
            $count = $this->cascading($model, $relation)->count();

            if ($count > 0) {
                error('data-in-use', extra: ['count' => $count]);
            }
        }
    }

    /**
     * @param list<string> $chain
     */
    private function purge(Model $model, array $chain): void {
        $name = array_shift($chain);

        if ($name === null) {
            return;
        }

        $excluded = array_slice($chain, 0, 1);

        foreach ($this->cascading($model, $name)->get() as $child) {
            $this->guardReferences($child, $excluded);

            $this->purge($child, $chain);

            $child->delete();
        }
    }

    /**
     * @return list<string>
     */
    private function referencingRelations(Model $model): array {
        $names = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            if ($method->getDeclaringClass()->getName() === $model::class
                && $method->getNumberOfParameters() === 0
                && $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), HasOneOrMany::class, true)) {
                $names[] = $method->getName();
            }
        }

        return $names;
    }

}
