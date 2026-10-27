<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InsertService extends CrudService {

    public function check(mixed $input): void {
        DB::beginTransaction();

        try {
            [$model, $form] = $this->fresh($input);

            $this->assign($form, $model, $this->validated($form, $input));
            $this->inspect($model);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function form(mixed $input): array {
        [$model, $form] = $this->fresh($input);

        return $this->payload($form->columns, $model);
    }

    /**
     * @return array{id: mixed}
     */
    public function insert(mixed $input): array {
        $model = $this->model->newInstance();

        $this->attach($model);

        $form = $this->expandComposites($input, $model);

        $this->store($form, $model, $input);

        return ['id' => $model->getKey()];
    }

    /**
     * @return array{Model, Form}
     */
    private function fresh(mixed $input): array {
        $model = $this->model->newInstance();
        $foreign = $this->foreign();

        if ($foreign !== null && array_get_value($this->params, $foreign) !== null) {
            $this->attach($model);
        }

        return [$model, $this->expandComposites($input, $model)];
    }

}
