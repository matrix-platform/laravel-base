<?php //>

namespace MatrixPlatform\Services\Admin\Common;

class NewService extends Service {

    private $actions = ['insert'];

    public function actions($actions) {
        $this->actions = $actions;

        return $this;
    }

    public function output() {
        $model = $this->model->newInstance();

        if ($model->getParent()) {
            $foreign = $model->{$model->getParent()}()->getForeignKeyName();
            $model->{$foreign} = data_get($this->params, $foreign);
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
            'subtitle' => $this->resolveSubtitle($parents),
            'breadcrumbs' => $this->getBreadcrumbs([$model, ...$parents]),
            'data' => array_merge(array_fill_keys(array_column($columns, 'name'), null), $model->toArray()),
            'columns' => array_merge([['name' => 'id', 'type' => 'hidden']], array_map($this->strip(...), $columns)),
            'actions' => $actions
        ];
    }

}
