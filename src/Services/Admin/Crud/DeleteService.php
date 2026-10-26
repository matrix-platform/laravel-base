<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use MatrixPlatform\Attributes\Unreferenced;
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
        $raw = Arr::wrap(array_get_value($values, 'id'));

        if (!$this->scalars($raw)) {
            invalid('id', 'array');
        }

        $items = array_values(array_unique($raw));
        $models = $this->plain()
            ->whereKey($items)
            ->get();

        if ($models->count() !== count($items)) {
            error('data-not-found', 404);
        }

        $branches = $this->branches(array_map(fn (string $relation): array => explode('.', $relation), $this->cascade));
        $counts = $this->referenceCounts($models, array_keys($branches));

        foreach ($models as $model) {
            $this->inspect($model);
            $this->guardReferences($model, array_keys($branches), $counts);
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
     * @param array<string, array{key: string, counts: array<string, int>}> $counts
     */
    private function guardReferences(Model $model, array $excluded, array $counts = []): void {
        foreach (array_diff($this->referencingRelations($model), $excluded) as $relation) {
            $count = $this->referenceCount($model, $relation, $counts);

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
     * @param array<string, array{key: string, counts: array<string, int>}> $counts
     */
    private function referenceCount(Model $model, string $relation, array $counts): int {
        if (!array_key_exists($relation, $counts)) {
            return $this->cascading($model, $relation)->count();
        }

        $key = $model->getAttribute($counts[$relation]['key']);

        return is_scalar($key) ? intval(array_get_value($counts[$relation]['counts'], strval($key), 0)) : 0;
    }

    /**
     * @template TModel of Model
     * @param Collection<int, TModel> $models
     * @param list<string> $excluded
     * @return array<string, array{key: string, counts: array<string, int>}>
     */
    private function referenceCounts(Collection $models, array $excluded): array {
        $first = $models->first();

        if ($first === null) {
            return [];
        }

        $counts = [];

        foreach (array_diff($this->referencingRelations($first), $excluded) as $name) {
            $relation = Relation::noConstraints(fn (): HasOneOrMany => $this->cascading($first, $name));

            $relation->addEagerConstraints($models->all());

            $query = $relation->getQuery()->toBase();

            if ($query->limit !== null || $query->offset !== null || $query->groups !== null || $query->havings !== null || $query->unions !== null || $query->joins !== null) {
                continue;
            }

            $foreign = $relation->getQualifiedForeignKeyName();
            $grouped = [];

            $rows = $query->reorder()
                ->select("{$foreign} as reference")
                ->selectRaw('count(*) as aggregate')
                ->groupBy($foreign)
                ->pluck('aggregate', 'reference');

            foreach ($rows as $reference => $aggregate) {
                $grouped[strval($reference)] = intval($aggregate);
            }

            $counts[$name] = ['key' => $relation->getLocalKeyName(), 'counts' => $grouped];
        }

        return $counts;
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
                && is_a($type->getName(), HasOneOrMany::class, true)
                && $method->getAttributes(Unreferenced::class) === []) {
                $names[] = $method->getName();
            }
        }

        $this->relations[$model::class] = $names;

        return $names;
    }

}
