<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class UpdateService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function update(int|string $id, mixed $input): array {
        $model = $this->complete()->findOrFail($id);
        $values = $this->validated($input);
        $before = $model->toArray();

        foreach ($this->local() as $column) {
            if (!$column->readonly) {
                $model->setAttribute($column->name, $values[$column->name]);
            }
        }

        $this->inspect($model, $before);

        $model->save();

        return ['id' => $model->getKey()];
    }

}
