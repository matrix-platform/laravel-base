<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Columns\Query\Sort;
use MatrixPlatform\Columns\Query\Sorting;
use MatrixPlatform\Services\PreferenceService;

class ListService extends CrudService {

    /**
     * @var list<string|Operation>
     */
    private array $pageActions = ['new', 'delete', 'arrange'];

    /**
     * @var list<string|Operation>
     */
    private array $rowActions = ['edit', 'delete'];

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
        $query = $this->projection();

        (new Filtering())->apply($query, $this->plan(), array_get_value($values, 'filters'));

        $total = $query->count();
        $sorted = (new Sorting($this->sorting))->apply($query, $this->plan(), array_get_value($values, 'sort'));
        $pagination = $this->paginate($query, $values, $total, $preference);
        $rowActions = $this->permitted($this->rowActions, $prefix);
        $rows = $this->rows($query, $rowActions);
        $data = $context->toArray();

        return [
            'title' => $this->title(),
            'subtitle' => $this->subtitle($parents),
            'breadcrumbs' => $this->breadcrumbs([null, ...$parents], $context),
            'context' => $data === [] ? (object) [] : $data,
            'rows' => $rows,
            'columns' => $this->payload($this->columns, $context),
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
    private function rows(Builder $query, array $rowActions): array {
        $rows = [];

        foreach ($query->get() as $row) {
            $this->inspect($row);

            $data = array_intersect_key($row->toArray(), $row->getAttributes());
            $data['actions'] = array_map(fn (Operation $operation): string => $operation->type, $this->passing($rowActions, $row));

            $rows[] = $data;
        }

        return $rows;
    }

}
