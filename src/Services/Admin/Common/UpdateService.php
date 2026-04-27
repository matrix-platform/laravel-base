<?php //>

namespace MatrixPlatform\Services\Admin\Common;

use Illuminate\Support\Facades\Validator;

class UpdateService extends Service {

    public function output($id, $input) {
        $model = $this->createQuery()->findOrFail($id);

        Validator::make($input, $this->resolveRules())->validate();

        $before = $model->toArray();

        foreach ($this->columns as $column) {
            if (empty($column['readonly'])) {
                $model->{$column['name']} = $input[$column['name']];
            }
        }

        if ($this->guard) {
            ($this->guard)($model, $before);
        }

        $model->save();

        return ['id' => $model->id];
    }

}
