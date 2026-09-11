<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class GetService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $actions = ['cancel', 'update'];

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
        $names = [...$this->names($this->columns), 'id'];
        $foreign = $this->foreign();

        if ($foreign !== null) {
            $names[] = $foreign;
        }

        return [
            'title' => $this->title(),
            'subtitle' => $this->subject->title($model),
            'breadcrumbs' => $this->breadcrumbs([$model, ...$parents], $model),
            'data' => $this->dated(array_intersect_key($model->toArray(), array_flip($names)), $model, $this->columns),
            'columns' => $this->payload($this->columns, $model, null),
            'actions' => $this->operations($this->passing($this->traceable($this->actions), $model), $this->prefix())
        ];
    }

}
