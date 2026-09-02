<?php //>

namespace MatrixPlatform\Columns\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Columns\Column;

class QueryPlan {

    /**
     * @var array<string, string>
     */
    private array $fields = [];

    /**
     * @var array<string, Join>
     */
    private array $joins = [];

    /**
     * @var array<string, array{field: string, qualifier: string}>
     */
    private array $translatable = [];

    /**
     * @param list<Column> $columns
     */
    public function __construct(private Model $root, private array $columns, private ?string $required = null) {
        $this->build();
    }

    /**
     * @return list<Column>
     */
    public function columns(): array {
        return $this->columns;
    }

    /**
     * @return Builder<Model>
     */
    public function complete(): Builder {
        $query = $this->root->query();

        if ($this->joins === [] && $this->translatable === []) {
            return $query;
        }

        $selects = ["{$this->table()}.*"];

        foreach ($this->columns as $column) {
            if ($column->virtual) {
                continue;
            }

            if ($column->translatable && $column->expression->path !== []) {
                array_push($selects, ...$this->translatedSelects($column));
            }

            if ($column->expression->path !== [] || $column->translatable) {
                $selects[] = new Raw("{$this->fields[$column->name]} as {$column->name}");
            }
        }

        $this->apply($query);

        return $query->select($selects);
    }

    public function field(string $name): ?string {
        $field = array_get_value($this->fields, $name);

        return is_string($field) ? $field : null;
    }

    /**
     * @return Builder<Model>
     */
    public function projection(): Builder {
        $query = $this->root->query();
        $selects = ["{$this->table()}.id"];

        if ($this->required !== null) {
            $selects[] = "{$this->table()}.{$this->required}";
        }

        foreach ($this->columns as $column) {
            if ($column->virtual) {
                continue;
            }

            if (array_key_exists($column->name, $this->translatable)) {
                array_push($selects, ...$this->translatedSelects($column));
            }

            $selects[] = new Raw("{$this->fields[$column->name]} as {$column->name}");
        }

        $this->apply($query);

        return $query->select($selects);
    }

    public function qualify(string $field): string {
        return $this->wrap($this->table(), $field);
    }

    public function table(): string {
        return $this->root->getTable();
    }

    /**
     * @param Builder<Model> $query
     */
    private function apply(Builder $query): void {
        foreach ($this->joins as $join) {
            if ($join->aggregates !== []) {
                [$sub, $top] = $this->subquery($join);

                $query->leftJoinSub($sub, $join->alias, "{$join->alias}.{$top->key}", '=', "{$top->target}.{$top->foreign}");
            } elseif ($join->referenced) {
                $query->leftJoin("{$join->table} as {$join->alias}", "{$join->alias}.{$join->key}", '=', "{$join->target}.{$join->foreign}");
            }
        }
    }

    private function build(): void {
        $aggregated = [];
        $referenced = [];
        $structure = [];

        foreach ($this->columns as $column) {
            $expression = $column->expression;
            $alias = '';
            $current = $this->root;
            $target = $this->table();

            foreach ($expression->path as $token) {
                $relation = $this->relation($current, $token);
                $alias = $alias === '' ? $token : "{$alias}__{$token}";

                if (!array_key_exists($alias, $structure)) {
                    [$key, $foreign] = $this->keys($relation);

                    $structure[$alias] = [
                        'foreign' => $foreign,
                        'key' => $key,
                        'table' => $relation->getRelated()->getTable(),
                        'target' => $target
                    ];
                }

                if ($expression->aggregate === null) {
                    if ($relation instanceof HasMany) {
                        error('invalid-column-expression');
                    }

                    $referenced[$alias] = true;
                }

                $current = $relation->getRelated();
                $target = $alias;
            }

            $qualifier = $alias === '' ? $this->table() : $alias;

            if ($expression->aggregate === null) {
                if ($column->translatable) {
                    $qualified = $this->wrap($qualifier, "{$expression->field}__" . app()->getLocale());
                    $this->translatable[$column->name] = ['field' => strval($expression->field), 'qualifier' => $qualifier];
                } else {
                    $qualified = $this->wrap($qualifier, strval($expression->field));
                }

                $this->fields[$column->name] = $expression->function === null ? $qualified : "{$expression->function}({$qualified})";

                continue;
            }

            if ($column->translatable) {
                error('invalid-column-expression');
            }

            if (!array_key_exists($alias, $structure)) {
                error('invalid-column-expression');
            }

            $aggregated[$alias][] = new Aggregate($expression->aggregate, $column->name, $expression->field, $expression->conditions);
            $this->fields[$column->name] = $this->wrap($qualifier, $column->name);
        }

        foreach ($structure as $alias => $node) {
            $aggregates = array_key_exists($alias, $aggregated) ? $aggregated[$alias] : [];

            if (array_key_exists($alias, $referenced) && $aggregates !== []) {
                error('invalid-column-expression');
            }

            $this->joins[$alias] = new Join(
                $alias,
                $node['table'],
                $node['target'],
                $node['key'],
                $node['foreign'],
                array_key_exists($alias, $referenced),
                $aggregates
            );
        }
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     * @return array{string, string}
     */
    private function keys(Relation $relation): array {
        if ($relation instanceof MorphTo || $relation instanceof MorphOneOrMany) {
            error('invalid-column-expression');
        }

        if ($relation instanceof BelongsTo) {
            return [$relation->getOwnerKeyName(), $relation->getForeignKeyName()];
        }

        if ($relation instanceof HasOne || $relation instanceof HasMany) {
            return [$relation->getForeignKeyName(), $relation->getLocalKeyName()];
        }

        error('invalid-column-expression');
    }

    /**
     * @return Relation<Model, Model, mixed>
     */
    private function relation(Model $current, string $token): Relation {
        $relation = $current->isRelation($token) ? $current->{$token}() : null;

        if (!$relation instanceof Relation) {
            error('invalid-column-expression');
        }

        return $relation;
    }

    /**
     * @return array{QueryBuilder, Join}
     */
    private function subquery(Join $join): array {
        $sub = DB::table("{$join->table} as {$join->alias}");
        $top = $join;

        while ($top->target !== $this->table()) {
            $node = $this->joins[$top->target];

            $sub->join("{$node->table} as {$node->alias}", "{$top->alias}.{$top->key}", '=', "{$node->alias}.{$top->foreign}");

            $top = $node;
        }

        $group = "{$top->alias}.{$top->key}";

        $sub->select($group)->groupBy($group);

        foreach ($join->aggregates as $aggregate) {
            $sql = $aggregate->field === null ? 'count(*)' : "{$aggregate->aggregate}({$join->alias}.{$aggregate->field})";

            if ($aggregate->conditions === []) {
                $sub->addSelect(new Raw("{$sql} as {$aggregate->name}"));

                continue;
            }

            [$where, $bindings] = Conditions::compile($aggregate->conditions);

            $sub->addSelect(new Raw("{$sql} FILTER (WHERE {$where}) as {$aggregate->name}"))->addBinding($bindings, 'select');
        }

        return [$sub, $top];
    }

    /**
     * @return list<Raw>
     */
    private function translatedSelects(Column $column): array {
        ['field' => $field, 'qualifier' => $qualifier] = $this->translatable[$column->name];

        $selects = [];

        foreach (locales() as $locale) {
            $selects[] = new Raw("{$this->wrap($qualifier, "{$field}__{$locale}")} as {$column->name}__{$locale}");
        }

        return $selects;
    }

    private function wrap(string $qualifier, string $field): string {
        $grammar = $this->root->getConnection()->getQueryGrammar();

        return "{$grammar->wrap($qualifier)}.{$grammar->wrap($field)}";
    }

}
