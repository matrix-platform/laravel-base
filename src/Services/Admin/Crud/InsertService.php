<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class InsertService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function insert(mixed $input): array {
        $model = $this->model->newInstance();
        $values = $this->validated($input);

        foreach ($this->local() as $column) {
            if (!array_key_exists($column->name, $values)) {
                continue;
            }

            if (!$column->readonly) {
                $model->setAttribute($column->name, $values[$column->name]);
            }
        }

        $this->attach($model);
        $this->inspect($model);

        $model->save();

        return ['id' => $model->getKey()];
    }

}
