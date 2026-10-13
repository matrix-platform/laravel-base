<?php //>

namespace MatrixPlatform\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\Admin\CrudController;
use MatrixPlatform\Services\Admin\Crud\ImportService;

/**
 * @phpstan-require-extends CrudController
 */
trait ImportsRows {

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function import(Request $request): array {
        return $this->importer($request)->import($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('import/template')]
    public function importTemplate(Request $request): array {
        return $this->importer($request)->template();
    }

    private function importer(Request $request): ImportService {
        return $this->onImport($this->prepare(new ImportService($this->model), $request));
    }

}
