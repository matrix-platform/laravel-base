<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

class UpdateService extends CrudService {

    /**
     * @return array{id: mixed}
     */
    public function update(int|string $id, mixed $input): array {
        $model = $this->complete()->findOrFail($id);
        $values = $this->validated($input, $model, $id);
        $before = $model->toArray();

        foreach ($this->local() as $column) {
            if (!$column->readonly && $column->translatable) {
                $this->assignTranslated($model, $column, $values);

                continue;
            }

            if (!$column->readonly && array_key_exists($column->name, $values)) {
                $resolved = $column->variantGroup === null
                    ? $this->driveResolved($column, $values[$column->name])
                    : $this->compositeResolved($column, $values[$column->name], $input, $model);

                $model->setAttribute($column->name, $resolved);
            }
        }

        $this->inspect($model, $before);

        $model->save();

        return ['id' => $model->getKey()];
    }

}
