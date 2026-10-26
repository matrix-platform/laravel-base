<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateService extends CrudService {

    protected bool $movable = false;

    public function check(int|string $id, mixed $input): void {
        DB::beginTransaction();

        try {
            $model = $this->find($id);

            $this->expandComposites($input, $model);

            $values = $this->validated($input, $id);
            $before = $model->toArray();

            $this->assign($model, $values);
            $this->reattach($model, $values);
            $this->inspect($model, $before);
        } finally {
            DB::rollBack();
        }
    }

    public function find(int|string $id): Model {
        return $this->complete()->findOrFail($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function form(int|string $id, mixed $input): array {
        $model = $this->find($id);

        $this->expandComposites($input, $model);

        return $this->payload($this->columns, $model);
    }

    public function movable(bool $movable): static {
        $this->movable = $movable;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Model $model): array {
        $columns = $this->columns;

        $this->expandComposites(null, $model);

        $names = [...$this->names($this->columns), $model->getKeyName(), ...Arr::whereNotNull([$this->foreign()])];
        $row = $this->flattenComposites($this->dated(Arr::only($model->toArray(), $names), $model, $this->columns), $model);

        $this->swap($columns);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(?string $ranking): array {
        $table = $this->model->getTable();
        $key = $this->model->getKeyName();
        $query = $this->complete();
        $rows = [];

        if ($ranking !== null) {
            $query->orderBy("{$table}.{$ranking}");
        }

        foreach ($query->orderBy("{$table}.{$key}")->get() as $model) {
            $rows[] = $this->row($model);
        }

        return $rows;
    }

    /**
     * @return array{id: mixed}
     */
    public function update(int|string $id, mixed $input): array {
        $model = $this->complete()->findOrFail($id);

        $this->expandComposites($input, $model);

        $values = $this->validated($input, $id);
        $before = $model->toArray();

        $this->assign($model, $values);
        $this->reattach($model, $values);
        $this->inspect($model, $before);

        $model->save();

        return ['id' => $model->getKey()];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function reattach(Model $model, array $values): void {
        $foreign = $this->foreign();

        if (!$this->movable || $foreign === null || !array_key_exists($foreign, $values)) {
            $this->attach($model);

            return;
        }

        $relation = $this->subject->parent($model);
        $target = $values[$foreign];

        if ($relation === null) {
            return;
        }

        if (!is_scalar($target)) {
            invalid($foreign, 'invalid-parent');
        }

        $related = $relation->getRelated();
        $query = $related->newQuery();

        if (!($relation->getOwnerKeyName() === $related->getKeyName() ? $query->whereKey($target) : $query->where($relation->getQualifiedOwnerKeyName(), $target))->exists()) {
            invalid($foreign, 'invalid-parent');
        }

        if ($this->subject->recursive($model) && in_array($model->getKey(), array_map(fn (Model $parent): mixed => $parent->getKey(), $this->subject->parents($model, [$foreign => $target])), true)) {
            invalid($foreign, 'invalid-parent');
        }
    }

}
