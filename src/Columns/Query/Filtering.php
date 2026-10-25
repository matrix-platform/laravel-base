<?php //>

namespace MatrixPlatform\Columns\Query;

use Closure;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MatrixPlatform\Columns\ColumnType;

class Filtering {

    public static function formatted(mixed $value, string $format): bool {
        if (!is_string($value) || str_contains($value, "\0")) {
            return false;
        }

        $parsed = DateTime::createFromFormat($format, $value);

        return $parsed !== false && $parsed->format($format) === $value;
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, Closure> $handlers
     */
    public function apply(Builder $query, QueryPlan $plan, mixed $filters, array $handlers = []): void {
        if (!is_array($filters)) {
            return;
        }

        $allowed = [];

        foreach ($plan->columns() as $column) {
            $operators = array_values(Arr::wrap($column->op));

            if ($operators !== []) {
                $allowed[$column->name] = ['op' => $operators, 'type' => $column->type, 'stored' => !$column->virtual || $column->expression->path !== []];
            }
        }

        foreach ($filters as $name => $filter) {
            if (!is_string($name) || !is_array($filter) || !array_key_exists($name, $allowed)) {
                continue;
            }

            $operator = $this->operator($filter, $allowed[$name]['op']);

            if ($operator === null) {
                continue;
            }

            if (array_key_exists($name, $handlers)) {
                $handlers[$name]($query, [...$filter, 'op' => $operator]);

                continue;
            }

            $field = $allowed[$name]['stored'] ? $plan->field($name) : null;

            if ($field !== null) {
                $this->where($query, $field, $operator, $filter, $allowed[$name]['type']);
            }
        }
    }

    /**
     * @param Builder<Model> $query
     */
    private function between(Builder $query, string $field, mixed $from, mixed $to, string $placeholder): void {
        if ($from !== null && $to !== null) {
            $query->whereRaw(new Raw("{$field} BETWEEN {$placeholder} AND {$placeholder}"), [$from, $to]);
        } elseif ($from !== null) {
            $query->whereRaw(new Raw("{$field} >= {$placeholder}"), [$from]);
        } elseif ($to !== null) {
            $query->whereRaw(new Raw("{$field} <= {$placeholder}"), [$to]);
        }
    }

    private function bound(mixed $value, ColumnType $type): mixed {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            error('invalid-filter-value');
        }

        $valid = match ($type) {
            ColumnType::Boolean => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
            ColumnType::Date, ColumnType::DateTime => self::formatted($value, strval($type->format())) && $this->dated(strval($value), strval($type->format())),
            ColumnType::Float => is_numeric($value) && is_finite(floatval($value)),
            ColumnType::Integer => is_int($value) || (is_string($value) && $this->integral($value)),
            default => true
        };

        if (!$valid) {
            error('invalid-filter-value');
        }

        return match ($type) {
            ColumnType::Float => floatval($value),
            ColumnType::Integer => intval($value),
            default => $value
        };
    }

    private function dated(string $value, string $format): bool {
        $parsed = DateTime::createFromFormat($format, $value);

        return $parsed !== false && intval($parsed->format('Y')) >= 1;
    }

    private function escape(mixed $value): string {
        return Conditions::escape(is_scalar($value) ? strval($value) : '');
    }

    /**
     * @param Builder<Model> $query
     */
    private function inside(Builder $query, string $field, string $operator, mixed $value, ColumnType $type): void {
        $values = array_values(array_map(fn (mixed $item): mixed => $this->bound($item, $type), Arr::wrap($value)));

        if ($values === []) {
            if ($operator === 'in') {
                $query->whereRaw(new Raw('1 <> 1'));
            }

            return;
        }

        $keyword = $operator === 'in' ? 'IN' : 'NOT IN';

        $query->whereRaw(new Raw("{$field} {$keyword} (" . implode(',', array_fill(0, count($values), $this->placeholder($type))) . ')'), $values);
    }

    private function integral(string $value): bool {
        if (preg_match('/\A(-?)0*(\d+)\z/', $value, $matches) !== 1) {
            return false;
        }

        return strval(intval($value)) === $matches[1] . $matches[2];
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

    private function placeholder(ColumnType $type): string {
        return $type === ColumnType::Integer ? '?::bigint' : '?';
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $filter
     */
    private function where(Builder $query, string $field, string $operator, array $filter, ColumnType $type): void {
        if ($operator === 'between') {
            $from = $this->bound(array_get_value($filter, 'from'), $type);
            $to = $this->bound(array_get_value($filter, 'to'), $type);

            $this->between($query, $field, $from, $to, $this->placeholder($type));

            return;
        }

        $value = array_get_value($filter, 'value');

        if ($operator === 'in' || $operator === 'notIn') {
            $this->inside($query, $field, $operator, $value, $type);

            return;
        }

        $value = $this->bound($value, $type);
        $placeholder = $this->placeholder($type);

        [$clause, $bindings] = match ($operator) {
            'eq' => ["= {$placeholder}", [$value]],
            'neq' => ["!= {$placeholder}", [$value]],
            'contains' => ['ILIKE ?', ['%' . $this->escape($value) . '%']],
            'startsWith' => ['ILIKE ?', [$this->escape($value) . '%']],
            'endsWith' => ['ILIKE ?', ['%' . $this->escape($value)]],
            'gt' => ["> {$placeholder}", [$value]],
            'gte' => [">= {$placeholder}", [$value]],
            'lt' => ["< {$placeholder}", [$value]],
            'lte' => ["<= {$placeholder}", [$value]],
            'null' => ['IS NULL', []],
            'notNull' => ['IS NOT NULL', []],
            default => [null, []]
        };

        if ($clause !== null) {
            $query->whereRaw(new Raw("{$field} {$clause}"), $bindings);
        }
    }

}
