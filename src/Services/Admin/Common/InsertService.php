<?php //>

namespace MatrixPlatform\Services\Admin\Common;

use Illuminate\Support\Facades\Validator;

class InsertService extends Service {

    public function output($input) {
        $model = $this->model->newInstance();

        Validator::make($input, $this->resolveRules())->validate();

        foreach ($this->columns as $column) {
            if (empty($column['readonly'])) {
                $model->{$column['name']} = $input[$column['name']];
            }
        }

        if ($model->getParent()) {
            $foreign = $model->{$model->getParent()}()->getForeignKeyName();
            $model->{$foreign} = data_get($this->params, $foreign);
        }

        if ($this->guard) {
            ($this->guard)($model);
        }

        $model->save();

        return ['id' => $model->id];
    }

}
