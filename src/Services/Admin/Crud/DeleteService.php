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
     * @var array<class-string<Model>, list<string>>
     */
    private array $relations = [];

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
            ->whereIn($this->model->getQualifiedKeyName(), $items)
            ->get();

        if ($models->count() !== count($items)) {
            error('data-not-found', 404);
        }

        $branches = $this->branches(array_map(fn (string $relation): array => explode('.', $relation), $this->cascade));

        foreach ($models as $model) {
            $this->inspect($model);
            $this->guardReferences($model, array_keys($branches));
        }

        foreach ($models as $model) {
            $this->purge($model, $branches);

            $model->delete();
        }

        return ['id' => $items];
    }

    /**
     * @param list<list<string>> $chains
     * @return array<string, list<list<string>>>
     */
    private function branches(array $chains): array {
        $branches = [];

        foreach ($chains as $chain) {
            $name = array_shift($chain);

            if ($name === null) {
                continue;
            }

            if (!array_key_exists($name, $branches)) {
                $branches[$name] = [];
            }

            if ($chain !== []) {
                $branches[$name][] = $chain;
            }
        }

        return $branches;
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
     * @param array<string, list<list<string>>> $branches
     */
    private function purge(Model $model, array $branches): void {
        foreach ($branches as $name => $chains) {
            $children = $this->branches($chains);

            foreach ($this->cascading($model, $name)->get() as $child) {
                $this->guardReferences($child, array_keys($children));

                $this->purge($child, $children);

                $child->delete();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function referencingRelations(Model $model): array {
        if (array_key_exists($model::class, $this->relations)) {
            return $this->relations[$model::class];
        }

        $names = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            if ($method->getNumberOfParameters() === 0
                && $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), HasOneOrMany::class, true)) {
                $names[] = $method->getName();
            }
        }

        $this->relations[$model::class] = $names;

        return $names;
    }

}
