<?php //>

namespace MatrixPlatform\Http\Controllers\Admin\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Http\Controllers\Admin\CrudController;
use MatrixPlatform\Routing\ActionRoutes;
use MatrixPlatform\Services\Admin\Crud\CrudService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\SortService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\StagedContext;
use MatrixPlatform\Support\Subject;

/**
 * @phpstan-require-extends CrudController
 */
trait StagesChildren {

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/staged/{key}')]
    public function stagedEdit(Request $request): array {
        [$child, $prefix, $foreign] = $this->stagedChild($request);
        $parent = $this->stagedParent($request);

        $this->stagedPermits($child, $prefix, 'insert', 'update', 'delete');

        $id = $this->optionalString($request, 'id');
        $updater = $child->onUpdate($this->stagedPrepare(new UpdateService($child->instance()::class), $prefix, $request, $foreign, $parent));
        $rows = $updater->rows($child->sortable() ? $this->stagedRanking($child) : null);
        $columns = $id === null
            ? $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, $parent))->form($request->input('data'))
            : $updater->form($id, $request->input('data'));
        $listing = $this->stagedListing($child, $prefix, $request, $foreign, $parent);
        $key = $child->instance()->getKeyName();

        $rows = array_map(fn (array $row): array => $this->stagedListed($row, $listing['rows'], $key), $rows);

        return [...$this->stagedForm($child, $prefix, $columns, $rows, $parent), 'list' => Arr::only($listing, ['columns', 'sorting'])];
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/staged/{key}/validate')]
    public function stagedEditValidate(Request $request): array {
        [$child, $prefix, $foreign] = $this->stagedChild($request);
        $parent = $this->stagedParent($request);
        $id = $this->optionalString($request, 'id');

        $this->stagedPermits($child, $prefix, $id === null ? 'insert' : 'update');

        return $this->stagedValidated(function () use ($child, $prefix, $foreign, $parent, $id, $request): array {
            if ($child instanceof WritesStagedRows) {
                $data = $request->input('data');
                $data = is_array($data) ? $data : [];
                $updater = $child->onUpdate($this->stagedPrepare(new UpdateService($child->instance()::class), $prefix, $request, $foreign, $parent));

                DB::beginTransaction();

                try {
                    $written = $id === null ? $child->stagedInsertRow($data) : $child->stagedUpdateRow($id, $data);
                    $listing = $this->stagedListing($child, $prefix, $request, $foreign, $parent, $written->getKey());

                    return $this->stagedListed($updater->row($updater->find(strval($written->getKey()))), $listing['rows'], $written->getKeyName());
                } finally {
                    DB::rollBack();
                }
            }

            if ($id === null) {
                $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, $parent))->check($request->input('data'));
            } else {
                $child->onUpdate($this->stagedPrepare(new UpdateService($child->instance()::class), $prefix, $request, $foreign, $parent))->check($id, $request->input('data'));
            }

            return [];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('staged/{key}')]
    public function stagedNew(Request $request): array {
        [$child, $prefix, $foreign] = $this->stagedChild($request);

        $this->stagedPermits($child, $prefix, 'insert');

        $columns = $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, null))->form($request->input('data'));

        return $this->stagedForm($child, $prefix, $columns, [], null);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('staged/{key}/validate')]
    public function stagedNewValidate(Request $request): array {
        [$child, $prefix, $foreign] = $this->stagedChild($request);

        $this->stagedPermits($child, $prefix, 'insert');

        return $this->stagedValidated(function () use ($child, $prefix, $foreign, $request): array {
            $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, null))->check($request->input('data'));

            return [];
        });
    }

    protected function afterStaged(string $key, Model $parent): void {}

    /**
     * @param Closure(): array<string, mixed> $write
     * @return array<string, mixed>
     */
    protected function persist(Request $request, Closure $write): array {
        $body = $request->all();
        $keys = array_values(array_intersect(array_keys($this->staged()), array_keys($body)));

        if ($keys === []) {
            return $write();
        }

        $children = $this->stagedChildren();
        $context = app(StagedContext::class);
        $result = null;

        $context->parent = Arr::except($body, $keys);

        $errors = $this->stagedAttempt(function () use ($write, &$result): void {
            $result = $write();
        });

        $id = is_array($result) ? array_get_value($result, 'id') : null;
        $id = is_int($id) || is_string($id) ? $id : null;
        $parent = $id === null ? ($request->route('id') === null ? null : $this->stagedParent($request)) : $this->instance()->newQuery()->findOrFail($id);

        $context->model = $parent;

        foreach ($keys as $key) {
            $errors = array_merge($errors, $this->stagedSave($key, $children[$key], $body[$key], $request, $parent));
        }

        if ($errors !== [] || !is_array($result) || $parent === null) {
            error('validation-failed', 422, ['fields' => $errors]);
        }

        foreach ($keys as $key) {
            $this->validateStaged($key, $parent);
        }

        foreach ($keys as $key) {
            $this->afterStaged($key, $parent);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected function staged(): array {
        return [];
    }

    protected function validateStaged(string $key, Model $parent): void {}

    /**
     * @param Closure(): mixed $write
     * @return array<string, list<string>>
     */
    private function stagedAttempt(Closure $write, ?string $prefix = null, bool $whole = false): array {
        try {
            DB::transaction($write);

            return [];
        } catch (ValidationException $exception) {
            $fields = validation_fields($exception);
        } catch (ServiceException $exception) {
            $extra = array_get_value($exception->getExtra(), 'fields');

            if (is_array($extra)) {
                $fields = [];

                foreach ($extra as $field => $slugs) {
                    $fields[strval($field)] = array_values(array_map(strval(...), Arr::wrap($slugs)));
                }
            } elseif ($whole && $prefix !== null && $exception->getError() !== 'data-not-found') {
                return [$prefix => [$exception->getError()]];
            } else {
                throw $exception;
            }
        }

        if ($prefix === null) {
            return $fields;
        }

        if ($whole) {
            return [$prefix => array_values(array_unique(array_merge(...array_values($fields))))];
        }

        $rewritten = [];

        foreach ($fields as $field => $slugs) {
            $rewritten["{$prefix}.{$field}"] = $slugs;
        }

        return $rewritten;
    }

    /**
     * @return array{CrudController, string, string}
     */
    private function stagedChild(Request $request): array {
        $found = array_get_value($this->stagedChildren(), strval($request->route('key')));

        if ($found === null) {
            error('data-not-found', 404);
        }

        app(StagedContext::class)->parent = Arr::wrap($request->input('parent'));

        return $found;
    }

    /**
     * @return array<string, array{CrudController, string, string}>
     */
    private function stagedChildren(): array {
        $declared = $this->staged();
        $fields = array_map(fn (string|array $column): string => (new ColumnParser())->parse($column)->name, [...$this->forming(), ...$this->updates()]);
        $resolved = [];

        foreach ($declared as $key => $prefix) {
            if (in_array($key, $fields, true)) {
                error('staged-key-conflict');
            }

            $class = ActionRoutes::controller($prefix);
            $child = $class === null ? null : app($class);
            $foreign = $child instanceof CrudController && !$child->standalone() ? app(Subject::class)->foreign($child->instance()) : null;

            if ($child === null || $foreign === null) {
                error('invalid-staged-child');
            }

            $resolved[$key] = [$child, $prefix, $foreign];
        }

        return $resolved;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function stagedForm(CrudController $child, string $prefix, array $columns, array $rows, ?Model $parent): array {
        $menu = app(Menus::class)->node($prefix);

        return [
            'title' => $menu === null ? null : i18n($menu->token()),
            'sortable' => $child->sortable(),
            'abilities' => $child instanceof WritesStagedRows && $parent !== null ? $child->stagedAbilities($parent) : ['insert' => true, 'update' => true, 'delete' => true],
            'columns' => $columns,
            'rows' => $rows
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<array-key, mixed>> $listed
     * @return array<string, mixed>
     */
    private function stagedListed(array $row, array $listed, string $key): array {
        foreach ($listed as $values) {
            if (strval(array_get_value($values, $key)) === strval(array_get_value($row, $key))) {
                return [...Arr::except($values, ['actions']), ...$row];
            }
        }

        return $row;
    }

    /**
     * @return array{columns: mixed, sorting: mixed, rows: list<array<array-key, mixed>>}
     */
    private function stagedListing(CrudController $child, string $prefix, Request $request, string $foreign, Model $parent, mixed $id = null): array {
        $service = $child->onList($this->stagedPrepare(new ListService($child->instance()::class), $prefix, $request, $foreign, $parent))
            ->pageActions([])
            ->rowActions([])
            ->when($id !== null, fn (Builder $query) => $query->whereKey($id));
        $listing = $service->list(['size' => 0]);
        $rows = array_get_value($listing, 'rows');

        return [
            'columns' => array_get_value($listing, 'columns'),
            'sorting' => array_get_value($listing, 'sorting'),
            'rows' => is_array($rows) ? array_values(array_filter($rows, is_array(...))) : []
        ];
    }

    private function stagedParent(Request $request): Model {
        $parent = $this->onUpdate($this->prepare(new UpdateService($this->model), $request))->find($this->identifier($request));

        app(StagedContext::class)->model = $parent;

        return $parent;
    }

    private function stagedPermits(CrudController $child, string $prefix, string ...$operations): void {
        $permission = app(AdminPermission::class);

        foreach ($operations as $operation) {
            if ($permission->permits($prefix, $child instanceof WritesStagedRows ? $child->stagedPermissionTag($operation) : $operation)) {
                return;
            }
        }

        error('permission-denied', 403);
    }

    /**
     * @template TService of CrudService
     * @param TService $service
     * @return TService
     */
    private function stagedPrepare(CrudService $service, string $prefix, Request $request, string $foreign, ?Model $parent): CrudService {
        $route = $request->route();
        $params = Arr::except($route instanceof Route ? $route->parameters() : [], ['id', 'key']);

        if ($parent !== null) {
            $params[$foreign] = $parent->getKey();
        }

        return $service
            ->standalone(false)
            ->mount($prefix)
            ->params($params);
    }

    private function stagedRanking(CrudController $child): string {
        $ranking = $child->ranking();

        return $ranking === null ? 'ranking' : $ranking;
    }

    /**
     * @param array{CrudController, string, string} $resolved
     * @return array<string, list<string>>
     */
    private function stagedSave(string $key, array $resolved, mixed $input, Request $request, ?Model $parent): array {
        [$child, $prefix, $foreign] = $resolved;
        $values = is_array($input) ? $input : [];
        $rows = [];

        foreach (Arr::wrap(array_get_value($values, 'rows')) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $id = array_get_value($row, 'id');

            $rows[$index] = [is_scalar($id) ? strval($id) : null, $row];
        }

        $deletes = Arr::wrap(array_get_value($values, 'delete'));
        $order = array_get_value($values, 'order');
        $updated = array_filter($rows, fn (array $row): bool => $row[0] !== null);
        $inserted = array_filter($rows, fn (array $row): bool => $row[0] === null);

        if ($deletes !== []) {
            $this->stagedPermits($child, $prefix, 'delete');
        }

        if ($updated !== [] || $order !== null) {
            $this->stagedPermits($child, $prefix, 'update');
        }

        if ($inserted !== []) {
            $this->stagedPermits($child, $prefix, 'insert');
        }

        $errors = [];

        if ($parent === null) {
            foreach ($rows as $index => [, $row]) {
                $errors = array_merge($errors, $this->stagedAttempt(fn () => $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, null))->check($row), "{$key}.{$index}"));
            }

            return $errors;
        }

        foreach ($deletes as $id) {
            $id = is_scalar($id) ? strval($id) : '';

            $errors = array_merge($errors, $this->stagedAttempt(fn () => $child instanceof WritesStagedRows ? $child->stagedDeleteRow($id) : $child->onDelete($this->stagedPrepare(new DeleteService($child->instance()::class), $prefix, $request, $foreign, $parent))->delete(['id' => [$id]]), "{$key}.delete.{$id}", true));
        }

        $ids = [];

        foreach ($updated as $index => [$id, $row]) {
            $ids[$index] = strval($id);

            $errors = array_merge($errors, $this->stagedAttempt(fn () => $child instanceof WritesStagedRows ? $child->stagedUpdateRow(strval($id), $row) : $child->onUpdate($this->stagedPrepare(new UpdateService($child->instance()::class), $prefix, $request, $foreign, $parent))->update(strval($id), $row), "{$key}.{$index}"));
        }

        foreach ($inserted as $index => [, $row]) {
            $errors = array_merge($errors, $this->stagedAttempt(function () use ($child, $prefix, $request, $foreign, $parent, $row, $index, &$ids): void {
                $created = $child instanceof WritesStagedRows ? $child->stagedInsertRow($row)->getKey() : $child->onInsert($this->stagedPrepare(new InsertService($child->instance()::class), $prefix, $request, $foreign, $parent))->insert($row)['id'];

                $ids[$index] = strval(is_scalar($created) ? $created : '');
            }, "{$key}.{$index}"));
        }

        if ($order === null || $errors !== []) {
            return $errors;
        }

        if (!$child->sortable()) {
            error('data-not-found', 404);
        }

        $sequence = [];

        foreach (Arr::wrap($order) as $entry) {
            if (is_string($entry) && str_starts_with($entry, 'id:')) {
                $sequence[] = substr($entry, 3);
            } elseif (is_int($entry) && array_key_exists($entry, $ids)) {
                $sequence[] = $ids[$entry];
            }
        }

        return $this->stagedAttempt(fn () => $child->onSort($this->stagedPrepare(new SortService($child->instance()::class), $prefix, $request, $foreign, $parent))->sort(['order' => $sequence]), $key, true);
    }

    /**
     * @param Closure(): array<string, mixed> $check
     * @return array<string, mixed>
     */
    private function stagedValidated(Closure $check): array {
        try {
            return $check();
        } catch (ServiceException $exception) {
            $fields = array_get_value($exception->getExtra(), 'fields');

            if ($exception->getError() !== 'validation-failed' && is_array($fields)) {
                error('validation-failed', 422, ['fields' => $fields]);
            }

            throw $exception;
        }
    }

}
