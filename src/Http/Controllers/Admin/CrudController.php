<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Services\Admin\Crud\CrudService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\NewService;
use MatrixPlatform\Services\Admin\Crud\SortService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;

abstract class CrudController extends BaseController {

    protected bool $exportable = false;

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $exports = null;

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $inserts = null;

    /**
     * @var list<string|array<string, mixed>>|null
     */
    protected ?array $lists = null;

    /**
     * @var class-string<Model>
     */
    protected string $model;

    protected bool $sortable = false;

    /**
     * @var list<string>
     */
    protected array $sorting = [];

    protected bool $standalone = false;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = [];

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/copy')]
    public function copy(Request $request): array {
        return $this->onCopy($this->prepare(new CopyService($this->model), $request))->copy($this->identifier($request));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function delete(Request $request): array {
        return $this->onDelete($this->prepare(new DeleteService($this->model), $request))->delete($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function export(Request $request): array {
        if (!$this->exportable) {
            error('data-not-found', 404);
        }

        return $this->onExport($this->prepare(new ExportService($this->model), $request))->export($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}')]
    public function get(Request $request): array {
        return $this->onGet($this->prepare(new GetService($this->model), $request))->get($this->identifier($request));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function insert(Request $request): array {
        return $this->onInsert($this->prepare(new InsertService($this->model), $request))->insert($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('')]
    public function list(Request $request): array {
        return $this->onList($this->prepare(new ListService($this->model), $request))->list($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function new(Request $request): array {
        return $this->onNew($this->prepare(new NewService($this->model), $request))->new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function sort(Request $request): array {
        return $this->sorter($request)->items();
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('sort/save')]
    public function sortSave(Request $request): array {
        return $this->sorter($request)->sort($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/update')]
    public function update(Request $request): array {
        return $this->onUpdate($this->prepare(new UpdateService($this->model), $request))->update($this->identifier($request), $request->all());
    }

    protected function onCopy(CopyService $service): CopyService {
        return $service;
    }

    protected function onDelete(DeleteService $service): DeleteService {
        return $service;
    }

    protected function onExport(ExportService $service): ExportService {
        return $service
            ->columns($this->exporting())
            ->filterColumns($this->listing())
            ->sorting($this->sorting);
    }

    protected function onGet(GetService $service): GetService {
        return $service->columns($this->updates);
    }

    protected function onInsert(InsertService $service): InsertService {
        return $service->columns($this->forming());
    }

    protected function onList(ListService $service): ListService {
        return $service->columns($this->listing())->sorting($this->sorting);
    }

    protected function onNew(NewService $service): NewService {
        return $service->columns($this->forming());
    }

    protected function onSort(SortService $service): SortService {
        return $service;
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return $service->columns($this->updates);
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function exporting(): array {
        return $this->exports === null ? $this->listing() : $this->exports;
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function forming(): array {
        return $this->inserts === null ? $this->updates : $this->inserts;
    }

    private function identifier(Request $request): string {
        return strval($request->route('id'));
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function listing(): array {
        return $this->lists === null ? $this->updates : $this->lists;
    }

    /**
     * @template TService of CrudService
     * @param TService $service
     * @return TService
     */
    private function prepare(CrudService $service, Request $request): CrudService {
        $route = $request->route();

        return $service->standalone($this->standalone)->params($route instanceof Route ? $route->parameters() : []);
    }

    private function sorter(Request $request): SortService {
        if (!$this->sortable) {
            error('data-not-found', 404);
        }

        return $this->onSort($this->prepare(new SortService($this->model), $request));
    }

}
