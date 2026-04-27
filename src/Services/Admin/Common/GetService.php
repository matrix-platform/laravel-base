<?php //>

namespace MatrixPlatform\Services\Admin\Common;

class GetService extends Service {

    private $actions = ['update'];

    public function actions($actions) {
        $this->actions = $actions;

        return $this;
    }

    public function output($id) {
        $model = $this->createQuery()->findOrFail($id);

        if ($this->guard) {
            ($this->guard)($model);
        }

        $actions = [];
        $columns = $this->resolveColumns($model);
        $parents = $this->getParents($model, $model);
        $prefix = $this->getPrefix($model);

        foreach ($this->actions as $action) {
            $action = $this->normalizeAction($prefix, $action);

            if (empty($action['$when']) || $action['$when']($model)) {
                $actions[] = $this->strip($action);
            }
        }

        return [
            'title' => $this->getTitle(),
            'subtitle' => $model->toTitle(),
            'breadcrumbs' => $this->getBreadcrumbs([$model, ...$parents]),
            'data' => array_intersect_key($model->toArray(), array_flip(array_merge(['id'], array_column($columns, 'name')))),
            'columns' => array_merge([['name' => 'id', 'type' => 'hidden']], array_map($this->strip(...), $columns)),
            'actions' => $actions
        ];
    }

}
