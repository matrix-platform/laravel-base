<?php //>

namespace MatrixPlatform\Services\Admin\Common;

class DeleteService extends Service {

    private $cascade = [];

    public function cascade($relations) {
        $this->cascade = $relations;

        return $this;
    }

    public function output($input) {
        $items = array_unique((array) data_get($input, 'id'));
        $models = $this->createQuery()->whereIn('id', $items)->get();

        if ($models->count() !== count($items)) {
            error('data-not-found');
        }

        if ($this->guard) {
            foreach ($models as $model) {
                ($this->guard)($model);
            }
        }

        foreach ($models as $model) {
            foreach ($this->cascade as $relation) {
                $this->deleteCascade($model, explode('.', $relation));
            }

            $model->delete();
        }

        return ['id' => $items];
    }

    private function deleteCascade($model, $chain) {
        $relation = array_shift($chain);
        $children = $model->$relation()->get();

        foreach ($children as $child) {
            if ($chain) {
                $this->deleteCascade($child, $chain);
            }

            $child->delete();
        }
    }

}
