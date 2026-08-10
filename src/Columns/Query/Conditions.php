<?php //>

namespace MatrixPlatform\Columns\Query;

use MatrixPlatform\Columns\Syntax\Condition;

class Conditions {

    /**
     * @param array<string, list<Condition>> $conditions
     * @return array{string, list<mixed>}
     */
    public static function compile(array $conditions): array {
        $bindings = [];
        $clauses = [];

        foreach ($conditions as $alias => $items) {
            foreach ($items as $condition) {
                $field = "{$alias}.{$condition->field}";
                $operator = $condition->operator;

                if ($operator === 'NULL' || $operator === 'NOT NULL') {
                    $clauses[] = "{$field} IS {$operator}";

                    continue;
                }

                if ($operator === 'IN' || $operator === 'NOT IN') {
                    $values = is_array($condition->value) ? $condition->value : [];
                    $bindings = array_merge($bindings, $values);
                    $clauses[] = "{$field} {$operator} (" . self::placeholders($values) . ')';

                    continue;
                }

                $value = is_string($condition->value) ? $condition->value : '';

                if ($operator === '^=' || $operator === '$=' || $operator === '*=') {
                    $escaped = self::escape($value);

                    $bindings[] = match ($operator) {
                        '^=' => "{$escaped}%",
                        '$=' => "%{$escaped}",
                        '*=' => "%{$escaped}%"
                    };

                    $clauses[] = "{$field} ILIKE ?";

                    continue;
                }

                $bindings[] = $value;
                $clauses[] = "{$field} {$operator} ?";
            }
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    public static function escape(string $value): string {
        return addcslashes($value, '%_\\');
    }

    /**
     * @param array<mixed> $values
     */
    public static function placeholders(array $values): string {
        return implode(',', array_fill(0, count($values), '?'));
    }

}
