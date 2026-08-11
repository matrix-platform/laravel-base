<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use MatrixPlatform\Columns\Column;

class GetService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $actions = ['update'];

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
    public function get(int|string $id): array {
        $model = $this->complete()->findOrFail($id);

        $this->inspect($model);

        $parents = $this->subject->parents($model, $model);
        $names = array_map(fn (Column $column): string => $column->name, $this->columns);

        return [
            'title' => $this->title(),
            'subtitle' => $this->subject->title($model),
            'breadcrumbs' => $this->breadcrumbs([$model, ...$parents], $model),
            'data' => array_intersect_key($model->toArray(), array_flip($names)),
            'columns' => $this->payload($this->columns, $model),
            'actions' => $this->operations($this->passing($this->actions, $model), $this->prefix())
        ];
    }

}
