<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class UpdateService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function update(int|string $id, mixed $input): array {
        $model = $this->complete()->findOrFail($id);

        $this->expandComposites($input, $model);

        $values = $this->validated($input, $id);
        $before = $model->toArray();

        $this->assign($model, $values);
        $this->inspect($model, $before);

        $model->save();

        return ['id' => $model->getKey()];
    }

}
