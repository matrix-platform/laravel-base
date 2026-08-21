<?php //>

namespace MatrixPlatform\Columns\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Filtering {

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, QueryPlan $plan, mixed $filters): void {
        if (!is_array($filters)) {
            return;
        }

        $allowed = [];

        foreach ($plan->columns() as $column) {
            $operators = array_values(Arr::wrap($column->op));

            if ($operators !== []) {
                $allowed[$column->name] = $operators;
            }
        }

        foreach ($filters as $name => $filter) {
            if (!is_string($name) || !is_array($filter) || !array_key_exists($name, $allowed)) {
                continue;
            }

            $field = $plan->field($name);
            $operator = $this->operator($filter, $allowed[$name]);

            if ($field !== null && $operator !== null) {
                $this->where($query, $field, $operator, $filter);
            }
        }
    }

    /**
     * @param Builder<Model> $query
     */
    private function between(Builder $query, string $field, mixed $from, mixed $to): void {
        if ($from !== null && $to !== null) {
            $query->whereRaw(new Raw("{$field} BETWEEN ? AND ?"), [$from, $to]);
        } elseif ($from !== null) {
            $query->whereRaw(new Raw("{$field} >= ?"), [$from]);
        } elseif ($to !== null) {
            $query->whereRaw(new Raw("{$field} <= ?"), [$to]);
        }
    }

    private function escape(mixed $value): string {
        return Conditions::escape(is_scalar($value) ? strval($value) : '');
    }

    /**
     * @param Builder<Model> $query
     */
    private function inside(Builder $query, string $field, string $operator, mixed $value): void {
        $values = array_values(Arr::wrap($value));

        if ($values === []) {
            if ($operator === 'in') {
                $query->whereRaw(new Raw('1 <> 1'));
            }

            return;
        }

        $keyword = $operator === 'in' ? 'IN' : 'NOT IN';

        $query->whereRaw(new Raw("{$field} {$keyword} (" . Conditions::placeholders($values) . ')'), $values);
    }

    /**
     * @param array<string, mixed> $filter
     * @param list<string> $allowed
     */
    private function operator(array $filter, array $allowed): ?string {
        $operator = array_get_value($filter, 'op');

        if (!is_string($operator) || !in_array($operator, $allowed, true)) {
            return null;
        }

        if (array_get_value($filter, 'value') !== null) {
            return $operator;
        }

        return match ($operator) {
            'eq', 'null' => 'null',
            'neq', 'notNull' => 'notNull',
            'between', 'in' => $operator,
            default => null
        };
    }

    private function shaped(mixed $value): void {
        if ($value !== null && !is_scalar($value)) {
            error('invalid-filter-value', 422);
        }
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $filter
     */
    private function where(Builder $query, string $field, string $operator, array $filter): void {
        if ($operator === 'between') {
            $from = array_get_value($filter, 'from');
            $to = array_get_value($filter, 'to');

            $this->shaped($from);
            $this->shaped($to);

            $this->between($query, $field, $from, $to);

            return;
        }

        $value = array_get_value($filter, 'value');

        if ($operator === 'in' || $operator === 'notIn') {
            foreach (Arr::wrap($value) as $item) {
                $this->shaped($item);
            }

            $this->inside($query, $field, $operator, $value);

            return;
        }

        $this->shaped($value);

        [$clause, $bindings] = match ($operator) {
            'eq' => ['= ?', [$value]],
            'neq' => ['!= ?', [$value]],
            'contains' => ['ILIKE ?', ['%' . $this->escape($value) . '%']],
            'startsWith' => ['ILIKE ?', [$this->escape($value) . '%']],
            'endsWith' => ['ILIKE ?', ['%' . $this->escape($value)]],
            'gt' => ['> ?', [$value]],
            'gte' => ['>= ?', [$value]],
            'lt' => ['< ?', [$value]],
            'lte' => ['<= ?', [$value]],
            'null' => ['IS NULL', []],
            'notNull' => ['IS NOT NULL', []],
            default => [null, []]
        };

        if ($clause !== null) {
            $query->whereRaw(new Raw("{$field} {$clause}"), $bindings);
        }
    }

}
