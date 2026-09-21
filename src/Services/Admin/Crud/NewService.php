<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use MatrixPlatform\Columns\Column;

class NewService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $actions = ['cancel', 'insert'];

    /**
     * @param list<string|Operation> $actions
     */
    public function actions(array $actions): static {
        $this->actions = $actions;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function new(mixed $input = null): array {
        $model = $this->model->newInstance();

        $this->attach($model);
        $this->expandComposites($input, $model);

        $parents = $this->subject->parents($model, $model);
        $blank = array_fill_keys($this->names($this->local()), null);

        return [
            'title' => $this->title(),
            'subtitle' => $this->subtitle($parents),
            'breadcrumbs' => $this->breadcrumbs([$model, ...$parents], $model),
            'data' => array_merge($blank, $model->toArray(), $this->defaults($input)),
            'columns' => $this->payload($this->columns, $model),
            'actions' => $this->operations($this->passing($this->actions, $model), $this->prefix())
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(mixed $input): array {
        $values = is_array($input) ? $input : [];
        $writable = array_values(array_filter($this->local(), fn (Column $column): bool => $this->writable($column)));

        return array_intersect_key($values, array_flip($this->names($writable)));
    }

}
