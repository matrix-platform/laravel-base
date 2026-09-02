<?php //>

namespace MatrixPlatform\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\OptionProvider;
use MatrixPlatform\Columns\Options\RelationOptions;
use MatrixPlatform\Columns\Syntax\ParsedColumn;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\Subject;

class ColumnResolver {

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $bundles = [];

    public function __construct(private MetadataRegistry $registry, private Resources $resources, private Menus $menus, private Subject $subject) {}

    public function resolve(ParsedColumn $column, Model $root): Column {
        $terminal = $this->terminal($root, $column->expression->path);
        $definitions = $this->registry->definitions($terminal::class);

        if ($definitions === null) {
            error('undeclared-model');
        }

        $definition = $this->definition($definitions, $column->expression->field);
        $cast = $this->cast($terminal, $column->expression->field);
        $type = $this->type($column, $definition, $cast);
        $declared = $this->presentation($column, $definition, $cast);
        $silent = $declared === Presentation::Hidden && !$this->searchable($column);
        $options = $silent ? $column->options : $this->options($column, $definition, $terminal, $type);
        $presentation = $this->shown($declared, $options);

        return new Column(
            $column->expression,
            $column->group,
            $column->name,
            $this->operator($column, $type, $presentation),
            $options,
            $this->path($column, $terminal),
            $silent ? $column->placeholder : $this->label($root, $column, $column->placeholder, ':placeholder'),
            $presentation,
            $column->readonly,
            $silent ? $column->remark : $this->label($root, $column, $column->remark, ':remark'),
            $column->required || ($definition !== null && $definition->required),
            $this->rule($column, $definition),
            $this->sortable($column, $type, $presentation),
            $silent ? $this->fallback($column) : $this->title($root, $column),
            $definition === null ? false : $definition->translatable,
            $type,
            $definition !== null && $definition->unique,
            $column->virtual
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bundle(Model $root): array {
        $table = $root->getTable();

        if (!array_key_exists($table, $this->bundles)) {
            $default = $this->resources->getI18nBundle('model/default');
            $specific = $this->resources->getI18nBundle("model/{$table}");

            $this->bundles[$table] = array_replace($default === null ? [] : $default, $specific === null ? [] : $specific);
        }

        return $this->bundles[$table];
    }

    private function cast(Model $terminal, ?string $field): ?string {
        if ($field === null) {
            return null;
        }

        $cast = array_get_value($terminal->getCasts(), $field);

        return is_string($cast) ? $cast : null;
    }

    /**
     * @param array<string, Definition> $definitions
     */
    private function definition(array $definitions, ?string $field): ?Definition {
        if ($field === null) {
            return null;
        }

        $definition = array_get_value($definitions, $field);

        return $definition instanceof Definition ? $definition : null;
    }

    private function fallback(ParsedColumn $column): string {
        return $column->title === null ? "{{$column->name}}" : $column->title;
    }

    private function label(Model $root, ParsedColumn $column, ?string $given, string $suffix): ?string {
        if ($given !== null) {
            return $given;
        }

        $found = array_get_value($this->bundle($root), "{$column->name}{$suffix}");

        return is_string($found) ? $found : null;
    }

    /**
     * @return string|list<string>|null
     */
    private function operator(ParsedColumn $column, ColumnType $type, Presentation|string $presentation): string|array|null {
        if ($column->opGiven) {
            return $column->op;
        }

        if ($presentation === Presentation::Hidden) {
            return null;
        }

        if ($type === ColumnType::Boolean) {
            return 'eq';
        }

        if ($presentation === Presentation::Select || $presentation === Presentation::MultiSelect) {
            return 'in';
        }

        return match ($type) {
            ColumnType::Date, ColumnType::DateTime, ColumnType::Float, ColumnType::Integer => 'between',
            ColumnType::Text => 'contains',
            ColumnType::Json => null
        };
    }

    private function options(ParsedColumn $column, ?Definition $definition, Model $terminal, ColumnType $type): ?OptionProvider {
        if ($column->options !== null) {
            return $column->options;
        }

        if ($definition?->options !== null) {
            return is_string($definition->options) ? app($definition->options) : $definition->options;
        }

        if ($column->optionsName !== null) {
            return new BundleOptions($column->optionsName);
        }

        if ($type === ColumnType::Boolean) {
            return new BundleOptions('boolean');
        }

        $field = $column->expression->field;

        if ($field === null || !str_ends_with($field, '_id')) {
            return null;
        }

        $relation = substr($field, 0, -3);
        $related = $terminal->isRelation($relation) ? $terminal->{$relation}() : null;

        if (!$related instanceof Relation) {
            return null;
        }

        $model = $related->getRelated()::class;

        if ($this->registry->of($model) === null) {
            error('undeclared-model');
        }

        return new RelationOptions($model);
    }

    private function path(ParsedColumn $column, Model $terminal): ?string {
        if ($column->path !== null) {
            return $column->path;
        }

        if ($column->expression->aggregate !== 'count') {
            return null;
        }

        $prefix = $this->subject->prefix($terminal);

        return $this->menus->has($prefix) ? $this->subject->generic($prefix) : null;
    }

    private function presentation(ParsedColumn $column, ?Definition $definition, ?string $cast): Presentation|string|null {
        if ($column->presentation !== null) {
            return $column->presentation;
        }

        if ($column->expression->aggregate === 'count') {
            return Presentation::Count;
        }

        if ($definition?->presentation !== null) {
            return $definition->presentation;
        }

        return $cast === 'hashed' ? Presentation::Password : null;
    }

    /**
     * @return list<string>
     */
    private function rule(ParsedColumn $column, ?Definition $definition): array {
        if ($column->rule !== []) {
            return $column->rule;
        }

        $declared = $definition === null ? [] : $definition->rule;

        return $declared instanceof Closure ? $declared() : $declared;
    }

    private function searchable(ParsedColumn $column): bool {
        return $column->op !== null && $column->op !== [];
    }

    private function shown(Presentation|string|null $declared, ?OptionProvider $options): Presentation|string {
        if ($declared !== null) {
            return $declared;
        }

        return $options === null ? Presentation::Plain : Presentation::Select;
    }

    private function sortable(ParsedColumn $column, ColumnType $type, Presentation|string $presentation): bool {
        if ($column->sortable !== null) {
            return $column->sortable;
        }

        return $presentation !== Presentation::Hidden && $type !== ColumnType::Boolean && $type !== ColumnType::Json;
    }

    /**
     * @param list<string> $path
     */
    private function terminal(Model $root, array $path): Model {
        $current = $root;

        foreach ($path as $token) {
            $relation = $current->isRelation($token) ? $current->{$token}() : null;

            if (!$relation instanceof Relation) {
                error('invalid-column-expression');
            }

            $current = $relation->getRelated();
        }

        return $current;
    }

    private function title(Model $root, ParsedColumn $column): string {
        if ($column->title !== null) {
            return $column->title;
        }

        $found = array_get_value($this->bundle($root), $column->name);

        return is_string($found) ? $found : "{{$column->name}}";
    }

    private function type(ParsedColumn $column, ?Definition $definition, ?string $cast): ColumnType {
        if ($column->type !== null) {
            return $column->type;
        }

        $aggregated = match ($column->expression->aggregate) {
            'avg' => ColumnType::Float,
            'count', 'sum' => ColumnType::Integer,
            default => null
        };

        if ($aggregated !== null) {
            return $aggregated;
        }

        if ($definition !== null) {
            return $definition->type;
        }

        $derived = $cast === null ? null : ColumnType::fromCast($cast);

        return $derived === null ? ColumnType::Text : $derived;
    }

}
