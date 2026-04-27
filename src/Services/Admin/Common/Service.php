<?php //>

namespace MatrixPlatform\Services\Admin\Common;

use Closure;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Resources;

abstract class Service {

    protected $columns = [];
    protected $guard;
    protected $joins = [];
    protected $model;
    protected $params = [];
    protected $scopes = [];
    protected $table;

    public function __construct($model) {
        $this->model = new $model();
        $this->table = $this->model->getTable();
    }

    public function columns($columns) {
        foreach ($columns as $column) {
            if (!is_array($column)) {
                $column = ['name' => $column];
            }

            $name = $column['name'];

            if (str_starts_with($name, '*')) {
                $name = substr($name, 1);
                $column['required'] = true;
            } else if (str_starts_with($name, '!')) {
                $name = substr($name, 1);
                $column['readonly'] = true;
            }

            if (str_contains($name, ':')) {
                [$name, $column['type']] = explode(':', $name, 2);
            }

            if (str_contains($name, '=')) {
                [$name, $expression] = explode('=', $name, 2);
            } else {
                $expression = $name;
                $name = null;
            }

            $aggregate = null;
            $function = null;

            if (preg_match('/^(\w+)\(([\w.]+)\)$/', $expression, $matches)) {
                $function = strtolower($matches[1]);
                $expression = $matches[2];

                if (in_array($function, ['avg', 'count', 'max', 'min', 'sum'])) {
                    $aggregate = $function;
                }
            }

            $tokens = explode('.', $expression);
            $alias = '';
            $current = $this->model;
            $field = $aggregate === 'count' ? null : array_pop($tokens);
            $last = array_key_last($tokens);
            $target = $this->table;

            foreach ($tokens as $index => $token) {
                $alias = $alias ? "{$alias}__{$token}" : $token;
                $relation = $current->$token();

                if (empty($this->joins[$alias])) {
                    $isAgg = $aggregate && $index === $last;

                    $this->joins[$alias] = [
                        'agg' => $isAgg,
                        'foreign' => $isAgg ? $relation->getLocalKeyName() : $relation->getForeignKeyName(),
                        'key' => $isAgg ? $relation->getForeignKeyName() : $relation->getOwnerKeyName(),
                        'table' => $relation->getRelated()->getTable(),
                        'target' => $target
                    ];
                }

                $current = $relation->getRelated();
                $target = $alias;
            }

            $qualifier = $alias ?: $this->table;

            if ($alias) {
                $column['$join'] = true;
            }

            if ($aggregate) {
                $name = $name ?: implode('_', array_filter([$alias, $aggregate, $field]));
                $column['$alias'] = $alias;
                $column['$field'] = "{$qualifier}.{$name}";
                $column['$raw'] = match ($aggregate) { 'count' => 'count(*)', default => "{$aggregate}({$field})" };
                $column['type'] = match ($aggregate) { 'avg' => 'float', 'count' => 'count', 'sum' => 'integer', default => null };

                if ($aggregate === 'count') {
                    $path = $this->getPrefix($current);

                    if (isset(app(AdminPermission::class)->getMenus()[$path])) {
                        $column['path'] = $path;
                    }
                }
            } else {
                $name = $name ?: str_replace('.', '_', $expression);
                $column['$field'] = $function ? "{$function}({$qualifier}.{$field})" : "{$qualifier}.{$field}";
            }

            $column['name'] = $name;

            if (empty($column['type'])) {
                $column['type'] = $current->getType($field);
            }

            if (str_contains($column['type'], ':')) {
                [$column['type'], $column['options']] = explode(':', $column['type'], 2);
            }

            if ($column['type'] !== 'hidden') {
                if (empty($column['options'])) {
                    if ($field && str_ends_with($field, '_id')) {
                        $relation = substr($field, 0, -3);

                        if (method_exists($current, $relation)) {
                            $related = $current->$relation()->getRelated();

                            $column['options'] = fn () => $related::all()->map(fn ($item) => ['id' => $item->id, 'ranking' => $item->ranking ?: 0, 'title' => $item->toTitle()]);
                        }
                    }
                } else if (is_string($column['options'])) {
                    $options = [];

                    foreach (app(Resources::class)->getI18nBundle("options/{$column['options']}") as $id => $title) {
                        $options[] = ['id' => $id, 'ranking' => count($options), 'title' => $title];
                    }

                    $column['options'] = $options;
                }

                if (isset($column['options'])) {
                    $column['type'] = 'select';
                }

                if (empty($column['title'])) {
                    $column['title'] = i18n("model/{$this->table}.{$column['name']}");
                }
            }

            $this->columns[] = $column;
        }

        return $this;
    }

    public function guard($callback) {
        $this->guard = $callback;

        return $this;
    }

    public function params($params) {
        $this->params = $params;

        return $this;
    }

    public function scope($callback) {
        $this->scopes[] = $callback;

        return $this;
    }

    public function when($condition, $callback) {
        if ($condition) {
            $this->scopes[] = $callback;
        }

        return $this;
    }

    protected function createQuery($slim = false) {
        $query = $this->model->query();

        foreach ($this->scopes as $scope) {
            $scope($query);
        }

        if ($this->joins || $slim) {
            $selects = $slim ? ["{$this->table}.id"] : ["{$this->table}.*"];

            foreach ($this->columns as $column) {
                if ($slim || isset($column['$join'])) {
                    $selects[] = "{$column['$field']} as {$column['name']}";
                }
            }

            foreach ($this->joins as $alias => $join) {
                if (empty($join['agg'])) {
                    $query->leftJoin("{$join['table']} as {$alias}", "{$alias}.{$join['key']}", '=', "{$join['target']}.{$join['foreign']}");
                } else {
                    $sub = DB::table($join['table'])->select($join['key'])->groupBy($join['key']);

                    foreach ($this->columns as $column) {
                        if (data_get($column, '$alias') === $alias) {
                            $sub->addSelect(DB::raw("{$column['$raw']} as {$column['name']}"));
                        }
                    }

                    $query->leftJoinSub($sub, $alias, "{$alias}.{$join['key']}", '=', "{$join['target']}.{$join['foreign']}");
                }
            }

            $query->select($selects);
        }

        return $query;
    }

    protected function getBreadcrumbs($items) {
        $breadcrumbs = [];
        $permission = app(AdminPermission::class);
        $menu = $permission->getCurrentMenu();

        if ($menu) {
            $menus = $permission->getMenus();
            $path = $menu['path'];

            while ($menu) {
                if (empty($menu['tag'])) {
                    $breadcrumbs[] = ['title' => i18n($menu['title'])];
                } else {
                    $breadcrumbs[] = ['label' => data_get($items, count($breadcrumbs))?->toTitle(), 'path' => $path, 'title' => i18n($menu['title'])];
                }

                $path = data_get($menu, 'parent');
                $menu = $path ? data_get($menus, $path) : null;
            }
        }

        return array_reverse($breadcrumbs);
    }

    protected function getParents($model, $source) {
        $current = $model;
        $parents = [];

        while ($current->getParent()) {
            $relation = $current->{$current->getParent()}();
            $id = data_get($source, $relation->getForeignKeyName());
            $parent = $id ? $relation->getRelated()->find($id) : null;

            if (!$parent) {
                break;
            }

            $current = $parent;
            $source = $parent;

            $parents[] = $parent;
        }

        return $parents;
    }

    protected function getPrefix($model) {
        $alias = $model->getAlias();

        if (!$model->getParent()) {
            return $alias;
        }

        $relation = $model->{$model->getParent()}();
        $parent = $relation->getRelated();

        if ($parent instanceof $model) {
            return $alias;
        }

        return $this->getPrefix($parent) . "/{{$relation->getForeignKeyName()}}/{$alias}";
    }

    protected function getTitle() {
        $menu = app(AdminPermission::class)->getCurrentMenu();

        return $menu ? i18n($menu['title']) : null;
    }

    protected function normalizeAction($prefix, $action) {
        if (is_string($action)) {
            $action = ['type' => $action];
        }

        if (empty($action['title'])) {
            $action['title'] = i18n("actions.{$action['type']}");
        }

        $action = array_merge(cfg("actions.{$action['type']}", []), $action);

        if (!empty($action['url'])) {
            $action['url'] = str_replace('{prefix}', $prefix, $action['url']);
        }

        if (!empty($action['confirm'])) {
            $action['confirm'] = i18n($action['confirm']);
        }

        return $action;
    }

    protected function processColumn($column) {
        return $column;
    }

    protected function resolveColumns($model) {
        $columns = [];

        foreach ($this->columns as $column) {
            $options = data_get($column, 'options');

            if ($options instanceof Closure) {
                $column['options'] = $options($model);
            }

            $columns[] = $this->processColumn($column);
        }

        return $columns;
    }

    protected function resolveRules() {
        $rules = [];

        foreach ($this->columns as $column) {
            if (!empty($column['readonly'])) {
                continue;
            }

            $rule = (array) data_get($column, 'rule');

            if (!$rule) {
                $type = match ($column['type']) {
                    'boolean' => 'boolean',
                    'date', 'datetime' => 'date',
                    'float' => 'numeric',
                    'integer' => 'integer',
                    'json' => 'array',
                    'text', 'hashed' => 'string',
                    default => null
                };

                if ($type) {
                    $rule[] = $type;
                }
            }

            if (empty($column['required'])) {
                array_unshift($rule, 'present', 'nullable');
            } else {
                array_unshift($rule, 'required');
            }

            $rules[$column['name']] = $rule;
        }

        return $rules;
    }

    protected function resolveSubtitle($items) {
        foreach ($items as $item) {
            $title = $item->toTitle();

            if (!blank($title)) {
                return $title;
            }
        }

        return null;
    }

    protected function strip($data) {
        return array_filter($data, fn ($name) => !str_starts_with($name, '$'), ARRAY_FILTER_USE_KEY);
    }

}
