<?php //>

namespace MatrixPlatform\Columns\Syntax;

use Illuminate\Support\Arr;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\OptionProvider;
use MatrixPlatform\Columns\Presentation;

class ColumnParser {

    private const AGGREGATES = ['avg', 'count', 'max', 'min', 'sum'];

    /**
     * @param string|array<string, mixed> $column
     */
    public function parse(string|array $column): ParsedColumn {
        $given = is_array($column) ? $column : ['name' => $column];
        $name = array_get_value($given, 'name');

        if (!is_string($name)) {
            error('invalid-column-expression');
        }

        $readonly = array_get_value($given, 'readonly') === true;
        $required = array_get_value($given, 'required') === true;
        $virtual = array_get_value($given, 'virtual') === true;

        if (str_starts_with($name, '*')) {
            $name = substr($name, 1);
            $required = true;
        } elseif (str_starts_with($name, '!')) {
            $name = substr($name, 1);
            $readonly = true;
        } elseif (str_starts_with($name, '+')) {
            $name = substr($name, 1);
            $virtual = true;
        }

        $group = $this->text(array_get_value($given, 'group'));
        $type = $this->text(array_get_value($given, 'type'));

        if (str_contains($name, '#')) {
            [$name, $group] = explode('#', $name, 2);
        }

        if (str_contains($name, ':')) {
            [$name, $type] = explode(':', $name, 2);
        }

        $optionsName = null;

        if ($type !== null && str_contains($type, ':')) {
            [$type, $optionsName] = explode(':', $type, 2);
        }

        if (preg_match('/^(\w+)=(.+)$/u', $name, $matches)) {
            [, $alias, $source] = $matches;
        } else {
            $alias = null;
            $source = $name;
        }

        $expression = $this->expression($source);
        $sortable = array_get_value($given, 'sortable');

        return new ParsedColumn(
            $expression,
            $group,
            $this->name($alias, $expression),
            $this->operator($given),
            array_key_exists('op', $given),
            $this->provider(array_get_value($given, 'options')),
            $optionsName,
            $this->text(array_get_value($given, 'path')),
            $this->text(array_get_value($given, 'placeholder')),
            $this->presentation($type),
            $readonly,
            $this->text(array_get_value($given, 'remark')),
            $required,
            $this->rule(array_get_value($given, 'rule')),
            is_bool($sortable) ? $sortable : ($virtual ? false : null),
            $this->text(array_get_value($given, 'title')),
            $type === null ? null : ColumnType::tryFrom($type),
            $virtual
        );
    }

    /**
     * @param list<string> $clauses
     * @return list<Condition>
     */
    private function conditions(array $clauses): array {
        $conditions = [];

        foreach ($clauses as $clause) {
            if (!preg_match('/^\s*(\w+)\s*(\^=|\$=|\*=|>=|<=|!=|=|>|<)\s*(.*?)\s*$/u', $clause, $matches)) {
                error('invalid-column-condition');
            }

            [, $field, $operator, $value] = $matches;

            $conditions[] = match (true) {
                $operator !== '=' && $operator !== '!=' => new Condition($field, $operator, $value),
                $value === 'null' => new Condition($field, $operator === '=' ? 'NULL' : 'NOT NULL', null),
                str_contains($value, ',') => new Condition($field, $operator === '=' ? 'IN' : 'NOT IN', explode(',', $value)),
                default => new Condition($field, $operator, $value)
            };
        }

        return $conditions;
    }

    private function expression(string $source): Expression {
        $aggregate = null;
        $function = null;
        $tokens = null;

        /** @var array<string, list<string>> $clauses */
        $clauses = [];

        if (preg_match('/^(\w+)\((.+)\)$/u', $source, $matches)) {
            $function = strtolower($matches[1]);
            $source = $matches[2];

            if (in_array($function, self::AGGREGATES, true)) {
                $aggregate = $function;
            }
        }

        if ($aggregate !== null && preg_match('/^\w+(?:\[[^\]]+\])*(?:\.\w+(?:\[[^\]]+\])*)*$/u', $source)) {
            $tokens = [];

            preg_match_all('/(\w+)((?:\[[^\]]+\])*)/u', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $tokens[] = $match[1];

                if ($match[2] !== '') {
                    preg_match_all('/\[([^\]]+)\]/u', $match[2], $brackets);

                    $clauses[$match[1]] = $brackets[1];
                }
            }
        } elseif ($aggregate === null && preg_match('/^\w+(?:\.\w+)*$/u', $source)) {
            $tokens = explode('.', $source);
        }

        if ($tokens === null) {
            error('invalid-column-expression');
        }

        $field = $aggregate === 'count' ? null : array_pop($tokens);

        return new Expression($aggregate, $this->located($tokens, $clauses, $field), $field, $function, $tokens);
    }

    /**
     * @param list<string> $tokens
     * @param array<string, list<string>> $clauses
     * @return array<string, list<Condition>>
     */
    private function located(array $tokens, array $clauses, ?string $field): array {
        $alias = '';
        $conditions = [];

        foreach ($tokens as $token) {
            $alias = $alias === '' ? $token : "{$alias}__{$token}";

            if (array_key_exists($token, $clauses)) {
                $conditions[$alias] = $this->conditions($clauses[$token]);
            }
        }

        if ($field !== null && array_key_exists($field, $clauses)) {
            $previous = array_key_exists($alias, $conditions) ? $conditions[$alias] : [];

            $conditions[$alias] = array_merge($previous, $this->conditions($clauses[$field]));
        }

        return $conditions;
    }

    private function name(?string $alias, Expression $expression): string {
        if ($alias !== null) {
            return $alias;
        }

        if ($expression->aggregate === null) {
            return implode('_', array_filter([...$expression->path, $expression->field]));
        }

        return implode('_', array_filter([$expression->alias(), $expression->aggregate, $expression->field]));
    }

    /**
     * @param array<string, mixed> $given
     * @return string|list<string>|null
     */
    private function operator(array $given): string|array|null {
        $operator = array_get_value($given, 'op');

        if (is_string($operator)) {
            return $operator;
        }

        return is_array($operator) ? array_values(array_filter($operator, is_string(...))) : null;
    }

    private function presentation(?string $type): Presentation|string|null {
        if ($type === null || ColumnType::tryFrom($type) !== null) {
            return null;
        }

        $presentation = Presentation::tryFrom($type);

        return $presentation === null ? $type : $presentation;
    }

    private function provider(mixed $options): ?OptionProvider {
        return $options instanceof OptionProvider ? $options : null;
    }

    /**
     * @return list<string>
     */
    private function rule(mixed $rule): array {
        return array_values(array_filter(Arr::wrap($rule), is_string(...)));
    }

    private function text(mixed $value): ?string {
        return is_string($value) ? $value : null;
    }

}
