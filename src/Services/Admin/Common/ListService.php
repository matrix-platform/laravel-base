<?php //>

namespace MatrixPlatform\Services\Admin\Common;

use StdClass;

class ListService extends Service {

    private $pageActions = ['new', 'delete'];
    private $rowActions = ['edit', 'delete'];
    private $sorting = [];

    public function output($input) {
        $model = $this->model;
        $parents = $this->getParents($model, $this->params);
        $query = $this->createQuery(true);

        if ($model->getParent()) {
            $foreign = $model->{$model->getParent()}()->getForeignKeyName();
            $model->{$foreign} = data_get($this->params, $foreign);

            $query->where($foreign, $model->{$foreign});
        }

        $columns = $this->resolveColumns($model);

        $this->applyFilters($query, $columns, data_get($input, 'filters', []));

        $total = $query->count();
        $sorting = $this->applySorting($query, $columns, data_get($input, 'sort', []));
        $pagination = $this->applyPagination($query, $input, $total);

        $prefix = $this->getPrefix($model);
        $pageActions = array_map(fn ($action) => $this->normalizeAction($prefix, $action), $this->pageActions);
        $rowActions = array_map(fn ($action) => $this->normalizeAction($prefix, $action), $this->rowActions);

        $keys = array_flip(array_merge(['id'], array_column($columns, 'name')));

        $rows = $query->get()->map(function ($row) use ($keys, $rowActions) {
            if ($this->guard) {
                ($this->guard)($row);
            }

            $data = array_intersect_key($row->toArray(), $keys);

            if ($rowActions) {
                $data['$actions'] = $this->evalRowActions($row, $rowActions);
            }

            return $data;
        });

        return [
            'title' => $this->getTitle(),
            'subtitle' => $this->resolveSubtitle($parents),
            'breadcrumbs' => $this->getBreadcrumbs($parents),
            'context' => $model->toArray() ?: new StdClass(),
            'rows' => $rows,
            'columns' => array_merge([['name' => 'id', 'type' => 'hidden']], array_map($this->strip(...), $columns)),
            'sorting' => $sorting,
            'pagination' => $pagination,
            'actions' => [
                'page' => array_map($this->strip(...), $pageActions),
                'row' => array_map($this->strip(...), $rowActions)
            ]
        ];
    }

    public function pageActions($actions) {
        $this->pageActions = $actions;

        return $this;
    }

    public function rowActions($actions) {
        $this->rowActions = $actions;

        return $this;
    }

    public function sorting($sorting) {
        foreach ($sorting as $item) {
            $this->sorting[] = str_starts_with($item, '-') ? [substr($item, 1), 'desc'] : [$item, 'asc'];
        }

        return $this;
    }

    protected function processColumn($column) {
        if (empty($column['op'])) {
            $column['op'] = match ($column['type']) {
                'boolean' => 'eq',
                'count', 'date', 'datetime', 'float', 'integer' => 'between',
                'select' => 'in',
                'text' => 'contains',
                default => null
            };
        }

        if (!isset($column['sortable'])) {
            $column['sortable'] = !in_array($column['type'], ['boolean']);
        }

        return $column;
    }

    private function applyBetween($query, $field, $from, $to) {
        if ($from !== null && $to !== null) {
            $query->whereRaw("{$field} BETWEEN ? AND ?", [$from, $to]);
        } elseif ($from !== null) {
            $query->whereRaw("{$field} >= ?", [$from]);
        } elseif ($to !== null) {
            $query->whereRaw("{$field} <= ?", [$to]);
        }
    }

    private function applyFilters($query, $columns, $filters) {
        if (!is_array($filters) || !count($filters)) {
            return;
        }

        $filterable = [];

        foreach ($columns as $column) {
            $operator = data_get($column, 'op');

            if ($operator && data_get($column, 'type') !== 'hidden') {
                $filterable[$column['name']] = ['field' => $column['$field'], 'ops' => (array) $operator];
            }
        }

        foreach ($filters as $name => $filter) {
            if (empty($filterable[$name]) || !is_array($filter)) {
                continue;
            }

            $operator = data_get($filter, 'op');

            if (!in_array($operator, $filterable[$name]['ops'])) {
                continue;
            }

            $value = data_get($filter, 'value');

            if ($value === null) {
                $operator = match ($operator) { 'eq' => 'null', 'neq' => 'notNull', default => $operator };

                if (!in_array($operator, ['in', 'null', 'notNull', 'between'])) {
                    continue;
                }
            }

            $field = $filterable[$name]['field'];

            match ($operator) {
                'eq' => $query->whereRaw("{$field} = ?", [$value]),
                'neq' => $query->whereRaw("{$field} != ?", [$value]),
                'contains' => $query->whereRaw("{$field} LIKE ?", ['%' . addcslashes($value, '%_\\') . '%']),
                'startsWith' => $query->whereRaw("{$field} LIKE ?", [addcslashes($value, '%_\\') . '%']),
                'endsWith' => $query->whereRaw("{$field} LIKE ?", ['%' . addcslashes($value, '%_\\')]),
                'gt' => $query->whereRaw("{$field} > ?", [$value]),
                'gte' => $query->whereRaw("{$field} >= ?", [$value]),
                'lt' => $query->whereRaw("{$field} < ?", [$value]),
                'lte' => $query->whereRaw("{$field} <= ?", [$value]),
                'between' => $this->applyBetween($query, $field, data_get($filter, 'from'), data_get($filter, 'to')),
                'in' => $this->applyIn($query, $field, (array) $value),
                'notIn' => $this->applyNotIn($query, $field, (array) $value),
                'null' => $query->whereRaw("{$field} IS NULL"),
                'notNull' => $query->whereRaw("{$field} IS NOT NULL"),
                default => null
            };
        }
    }

    private function applyIn($query, $field, $value) {
        if (count($value)) {
            $query->whereIn($field, $value);
        } else {
            $query->whereRaw('1 <> 1');
        }
    }

    private function applyNotIn($query, $field, $value) {
        if (count($value)) {
            $query->whereNotIn($field, $value);
        }
    }

    private function applyPagination($query, $input, $total) {
        $page = intval(data_get($input, 'page', 1));
        $size = intval(data_get($input, 'size', 10));

        if ($page > 0 && $size > 0) {
            $query->offset(($page - 1) * $size)->limit($size);
        } else {
            $page = 1;
            $size = $total;
        }

        return ['page' => $page, 'size' => $size, 'total' => $total];
    }

    private function applySorting($query, $columns, $sorting) {
        $sortable = [];

        foreach ($columns as $column) {
            if (data_get($column, 'type') !== 'hidden' && data_get($column, 'sortable', true) !== false) {
                $sortable[$column['name']] = $column['$field'];
            }
        }

        $valid = [];

        foreach ($sorting as $item) {
            $name = data_get($item, 'name');
            $direction = strtolower(data_get($item, 'direction', 'asc'));

            if (empty($sortable[$name]) || !in_array($direction, ['asc', 'desc'])) {
                continue;
            }

            $query->orderByRaw("{$sortable[$name]} {$direction}");

            $valid[] = ['name' => $name, 'direction' => $direction];
        }

        $used = array_column($valid, 'name');

        foreach ($this->sorting as [$name, $direction]) {
            if (!in_array($name, $used) && isset($sortable[$name])) {
                $query->orderByRaw("{$sortable[$name]} {$direction}");
            }
        }

        return $valid;
    }

    private function evalRowActions($model, $rowActions) {
        $types = [];

        foreach ($rowActions as $action) {
            if (empty($action['$when']) || $action['$when']($model)) {
                $types[] = $action['type'];
            }
        }

        return $types;
    }

}
