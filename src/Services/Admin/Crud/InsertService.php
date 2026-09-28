<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class InsertService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function insert(mixed $input): array {
        $model = $this->model->newInstance();

        $this->attach($model);

        $this->expandComposites($input, $model);

        $this->store($model, $input);

        return ['id' => $model->getKey()];
    }

}
