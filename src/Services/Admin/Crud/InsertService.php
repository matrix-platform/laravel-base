<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class InsertService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function insert(mixed $input): array {
        $model = $this->model->newInstance();

        $this->expandComposites($input, $model);

        $values = $this->validated($input);

        $this->assign($model, $values);
        $this->attach($model);
        $this->inspect($model);

        $model->save();

        return ['id' => $model->getKey()];
    }

}
