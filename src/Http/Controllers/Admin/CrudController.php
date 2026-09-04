<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
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
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Subject;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

abstract class CrudController extends BaseController {

    protected ?bool $arrangeable = null;

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

    protected ?bool $sortable = null;

    /**
     * @var list<string>
     */
    protected array $sorting = [];

    protected bool $standalone = false;

    /**
     * @var list<string|array<string, mixed>>
     */
    protected array $updates = [];

    private ?Model $instance = null;

    /**
     * @var list<string>|null
     */
    private ?array $relations = null;

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function arrange(Request $request): array {
        return $this->arranger($request)->items();
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('arrange/save')]
    public function arrangeSave(Request $request): array {
        return $this->arranger($request)->save($request->all());
    }

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

    protected function identifier(Request $request): string {
        return strval($request->route('id'));
    }

    protected function onArrange(ArrangeService $service): ArrangeService {
        return $service->rankable($this->sortable());
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
            ->sorting($this->sorting());
    }

    protected function onGet(GetService $service): GetService {
        return $service->columns($this->updates());
    }

    protected function onInsert(InsertService $service): InsertService {
        return $service->columns($this->forming());
    }

    protected function onList(ListService $service): ListService {
        return $service->columns($this->listing())->sorting($this->sorting());
    }

    protected function onNew(NewService $service): NewService {
        return $service->columns($this->forming());
    }

    protected function onSort(SortService $service): SortService {
        return $service;
    }

    protected function onUpdate(UpdateService $service): UpdateService {
        return $service->columns($this->updates());
    }

    private function arrangeable(): bool {
        if ($this->arrangeable !== null) {
            return $this->arrangeable;
        }

        $metadata = app(MetadataRegistry::class)->of($this->model);

        return $metadata !== null && $metadata->enable !== null && $metadata->disable !== null;
    }

    private function arranger(Request $request): ArrangeService {
        if (!$this->arrangeable()) {
            error('data-not-found', 404);
        }

        return $this->onArrange($this->prepare(new ArrangeService($this->model), $request));
    }

    private function complex(Definition $definition): bool {
        return is_string($definition->presentation) || in_array($definition->presentation, [Presentation::Hidden, Presentation::Password], true);
    }

    /**
     * @return list<string>
     */
    private function derived(): array {
        $definitions = app(MetadataRegistry::class)->definitions($this->model);

        if ($definitions === null) {
            return [];
        }

        $foreign = app(Subject::class)->foreign($this->instance());
        $reserved = [...array_keys(Definitions::primaryKey()), ...array_keys(Definitions::auditings())];
        $excluded = Arr::whereNotNull([...$reserved, $this->ranking(), $foreign]);

        return array_values(array_diff(array_keys($definitions), $excluded));
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
        return $this->inserts === null ? $this->updates() : $this->inserts;
    }

    private function instance(): Model {
        if ($this->instance === null) {
            $model = $this->model;
            $this->instance = new $model();
        }

        return $this->instance;
    }

    private function isHasManyAccessor(ReflectionMethod $method): bool {
        $type = $method->getReturnType();

        return $method->getDeclaringClass()->getName() === $this->model
            && $method->getNumberOfParameters() === 0
            && $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), HasMany::class, true);
    }

    private function joined(string $name): string {
        if (!str_ends_with($name, '_id')) {
            return $name;
        }

        $relation = substr($name, 0, -3);
        $related = app(Subject::class)->belongsTo($this->instance(), $relation);

        if ($related === null) {
            return $name;
        }

        $metadata = app(MetadataRegistry::class)->of($related->getRelated()::class);

        return $metadata === null ? $name : "{$relation}.{$metadata->title}";
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function listing(): array {
        if ($this->lists !== null) {
            return $this->lists;
        }

        if ($this->updates !== []) {
            return $this->updates;
        }

        $found = app(MetadataRegistry::class)->definitions($this->model);
        $definitions = $found === null ? [] : $found;
        $names = [];

        foreach ($this->derived() as $name) {
            if (!$this->complex($definitions[$name])) {
                $names[] = $this->joined($name);
            }
        }

        return [...$names, ...$this->relations()];
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

    private function ranking(): ?string {
        return app(MetadataRegistry::class)->of($this->model)?->ranking;
    }

    /**
     * @return list<string>
     */
    private function relations(): array {
        if ($this->relations !== null) {
            return $this->relations;
        }

        $names = [];

        foreach ((new ReflectionClass($this->model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->isHasManyAccessor($method)) {
                $names[] = "count({$method->getName()})";
            }
        }

        return $this->relations = $names;
    }

    private function sortable(): bool {
        if ($this->sortable !== null) {
            return $this->sortable;
        }

        $ranking = $this->ranking();

        return $ranking !== null && ($this->sorting === [] || $this->sorting === [$ranking]);
    }

    private function sorter(Request $request): SortService {
        if (!$this->sortable()) {
            error('data-not-found', 404);
        }

        return $this->onSort($this->prepare(new SortService($this->model), $request));
    }

    /**
     * @return list<string>
     */
    private function sorting(): array {
        if ($this->sorting !== []) {
            return $this->sorting;
        }

        $ranking = $this->ranking();

        return $ranking === null ? ['id'] : [$ranking];
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function updates(): array {
        if ($this->updates !== []) {
            return $this->updates;
        }

        return $this->derived();
    }

}
