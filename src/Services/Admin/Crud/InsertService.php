<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InsertService extends CrudService {

    public function check(mixed $input): void {
        DB::beginTransaction();

        try {
            $model = $this->fresh($input);

            $this->assign($model, $this->validated($input));
            $this->inspect($model);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function form(mixed $input): array {
        $model = $this->fresh($input);

        return $this->payload($this->columns, $model);
    }

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

    private function fresh(mixed $input): Model {
        $model = $this->model->newInstance();
        $foreign = $this->foreign();

        if ($foreign !== null && array_get_value($this->params, $foreign) !== null) {
            $this->attach($model);
        }

        $this->expandComposites($input, $model);

        return $model;
    }

}
