<?php //>

namespace MatrixPlatform\Columns\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sorting {

    /**
     * @param list<string> $defaults
     */
    public function __construct(private array $defaults = []) {}

    /**
     * @param Builder<Model> $query
     * @return list<Sort>
     */
    public function apply(Builder $query, QueryPlan $plan, mixed $requested): array {
        $sortable = [];

        foreach ($plan->columns() as $column) {
            $field = $column->sortable ? $plan->field($column->name) : null;

            if ($field !== null) {
                $sortable[$column->name] = $field;
            }
        }

        $applied = [];

        foreach (is_array($requested) ? $requested : [] as $item) {
            $sort = $this->requested($item, $sortable);

            if ($sort !== null) {
                $query->orderBy(new Raw($sortable[$sort->name]), $sort->direction->value);

                $applied[] = $sort;
            }
        }

        $used = array_map(fn (Sort $sort) => $sort->name, $applied);

        foreach ($this->defaults as $default) {
            $sort = $this->preset($default);

            if (in_array($sort->name, $used, true)) {
                continue;
            }

            $field = array_key_exists($sort->name, $sortable) ? $sortable[$sort->name] : $this->qualified($plan, $sort->name);

            if ($field !== null) {
                $query->orderBy(new Raw($field), $sort->direction->value);
            }
        }

        return $applied;
    }

    private function preset(string $default): Sort {
        return str_starts_with($default, '-') ? new Sort(substr($default, 1), Direction::Desc) : new Sort($default, Direction::Asc);
    }

    private function qualified(QueryPlan $plan, string $name): ?string {
        return preg_match('/^\w+$/u', $name) === 1 ? "{$plan->table()}.{$name}" : null;
    }

    /**
     * @param array<string, string> $sortable
     */
    private function requested(mixed $item, array $sortable): ?Sort {
        if (!is_array($item)) {
            return null;
        }

        $name = array_get_value($item, 'name');

        if (!is_string($name) || !array_key_exists($name, $sortable)) {
            return null;
        }

        $direction = array_key_exists('direction', $item) ? $item['direction'] : 'asc';

        if (!is_string($direction)) {
            return null;
        }

        $resolved = Direction::tryFrom(strtolower($direction));

        return $resolved === null ? null : new Sort($name, $resolved);
    }

}
