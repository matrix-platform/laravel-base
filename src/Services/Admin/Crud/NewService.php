<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use MatrixPlatform\Columns\Column;

class NewService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $actions = ['insert'];

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
    public function new(): array {
        $model = $this->model->newInstance();

        $this->attach($model);

        $parents = $this->subject->parents($model, $model);
        $blank = array_fill_keys(array_map(fn (Column $column): string => $column->name, $this->local()), null);

        return [
            'title' => $this->title(),
            'subtitle' => $this->subtitle($parents),
            'breadcrumbs' => $this->breadcrumbs([$model, ...$parents], $model),
            'data' => array_merge($blank, $model->toArray()),
            'columns' => $this->payload($this->columns, $model),
            'actions' => $this->operations($this->passing($this->actions, $model), $this->prefix())
        ];
    }

}
