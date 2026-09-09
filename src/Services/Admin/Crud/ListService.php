<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Columns\Query\Sort;
use MatrixPlatform\Columns\Query\Sorting;
use MatrixPlatform\Services\PreferenceService;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Schedule;

class ListService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $pageActions = ['new', 'delete', 'arrange', 'sort'];

    /**
     * @var list<string|Operation>
     */
    private array $rowActions = ['edit', 'delete'];

    /**
     * @var list<string>
     */
    private array $selects = [];

    /**
     * @var list<string>
     */
    private array $sorting = [];

    /**
     * @return array<string, mixed>
     */
    public function list(mixed $input): array {
        $context = $this->model;

        $this->attach($context);

        $values = is_array($input) ? $input : [];
        $preference = app(PreferenceService::class)->get(actor()->requireCurrent());
        $parents = $this->subject->parents($context, $this->params);
        $prefix = $this->prefix();
        $key = $this->subject->key($prefix);
        $metadata = app(MetadataRegistry::class)->of($context::class);
        $enable = $metadata?->enable;
        $disable = $metadata?->disable;
        $arrangeable = $enable !== null && $disable !== null;
        $query = $this->projection();

        $this->select($query, $arrangeable ? [...$this->selects, $enable, $disable] : $this->selects);

        (new Filtering())->apply($query, $this->plan(), array_get_value($values, 'filters'));

        $total = $query->count();
        $sorted = (new Sorting($this->sorting))->apply($query, $this->plan(), array_get_value($values, 'sort'));
        $pagination = $this->paginate($query, $values, $total, $preference);
        $rowActions = $this->permitted($this->traceable($this->rowActions), $prefix);
        $rows = $this->rows($query, $rowActions, $enable, $disable);
        $columns = $arrangeable ? array_values(array_filter($this->columns, fn (Column $column): bool => $column->name !== $enable && $column->name !== $disable)) : $this->columns;
        $data = $context->toArray();

        return [
            'title' => $this->title(),
            'subtitle' => $this->subtitle($parents),
            'breadcrumbs' => $this->breadcrumbs([null, ...$parents], $context),
            'context' => $data === [] ? (object) [] : $data,
            'rows' => $rows,
            'columns' => $this->payload($columns, $context),
            'features' => $arrangeable ? ['arrange'] : [],
            'preference' => array_get_value($preference, "column:{$key}"),
            'sorting' => array_map(fn (Sort $sort): array => ['name' => $sort->name, 'direction' => $sort->direction->value], $sorted),
            'pagination' => $pagination,
            'actions' => [
                'page' => $this->operations($this->passing($this->pageActions, $context), $prefix),
                'row' => $this->normalized($rowActions, $prefix)
            ]
        ];
    }

    /**
     * @param list<string|Operation> $actions
     */
    public function pageActions(array $actions): static {
        $this->pageActions = $actions;

        return $this;
    }

    /**
     * @param list<string|Operation> $actions
     */
    public function rowActions(array $actions): static {
        $this->rowActions = $actions;

        return $this;
    }

    /**
     * @param list<string> $selects
     */
    public function selects(array $selects): static {
        $this->selects = $selects;

        return $this;
    }

    /**
     * @param list<string> $sorting
     */
    public function sorting(array $sorting): static {
        $this->sorting = $sorting;

        return $this;
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $values
     * @param array<string, mixed> $preference
     * @return array{page: int, size: int, total: int}
     */
    private function paginate(Builder $query, array $values, int $total, array $preference): array {
        $page = intval(array_get_value($values, 'page', 1));
        $size = intval(array_get_value($values, 'size', array_get_value($preference, 'listSize', 10)));

        if ($page > 0 && $size > 0) {
            $query->forPage($page, $size);

            return ['page' => $page, 'size' => $size, 'total' => $total];
        }

        return ['page' => 1, 'size' => $total, 'total' => $total];
    }

    /**
     * @param Builder<Model> $query
     * @param list<Operation> $rowActions
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $query, array $rowActions, ?string $enable, ?string $disable): array {
        $rows = [];

        foreach ($query->get() as $row) {
            $this->inspect($row);

            $data = $this->dated(array_intersect_key($row->toArray(), $row->getAttributes()), $row, $this->columns);

            if ($enable !== null && $disable !== null) {
                $data = Arr::except($data, [$enable, $disable]);
                $data['enabled'] = Schedule::isEnabled($row, $enable, $disable);
            }

            $data['actions'] = array_map(fn (Operation $operation): string => $operation->type, $this->passing($rowActions, $row));

            $rows[] = $data;
        }

        return $rows;
    }

    /**
     * @param Builder<Model> $query
     * @param list<string> $fields
     */
    private function select(Builder $query, array $fields): void {
        $present = array_column($this->columns, 'name');
        $missing = array_values(array_diff(array_unique($fields), $present));

        if ($missing !== []) {
            $query->addSelect(array_map(fn (string $field): string => "{$this->plan()->table()}.{$field}", $missing));
        }
    }

}
