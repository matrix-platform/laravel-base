<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\TypeResolver;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Services\FileService;
use MatrixPlatform\Support\Actions;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\Subject;

abstract class CrudService {

    /**
     * @var list<Column>
     */
    protected array $columns = [];

    /**
     * @var list<Closure>
     */
    protected array $guards = [];

    protected BaseModel $model;

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * @var list<Closure>
     */
    protected array $scopes = [];

    protected bool $standalone = false;

    protected Subject $subject;

    private ?QueryPlan $plan = null;

    private ColumnResolver $resolver;

    /**
     * @param class-string<BaseModel> $model
     */
    public function __construct(string $model) {
        $this->model = new $model();
        $this->resolver = app(ColumnResolver::class);
        $this->subject = app(Subject::class);
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    public function columns(array $columns): static {
        foreach ($columns as $column) {
            $resolved = $this->resolve($column);

            if (!$this->has($resolved->name)) {
                $this->columns[] = $resolved;
            }
        }

        $this->plan = null;

        return $this;
    }

    public function guard(Closure $guard): static {
        $this->guards[] = $guard;

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function params(array $params): static {
        $this->params = $params;

        return $this;
    }

    public function scope(Closure $scope): static {
        $this->scopes[] = $scope;

        return $this;
    }

    public function standalone(bool $standalone): static {
        $this->standalone = $standalone;

        return $this;
    }

    public function when(bool $condition, Closure $scope): static {
        if ($condition) {
            $this->scopes[] = $scope;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function assignTranslated(Model $model, Column $column, array $values): void {
        foreach ($this->translated($column) as $key) {
            if (array_key_exists($key, $values)) {
                $model->setAttribute($key, $values[$key]);
            }
        }
    }

    protected function attach(Model $model): void {
        $foreign = $this->foreign();

        if ($foreign !== null) {
            $model->setAttribute($foreign, $this->owner($foreign));
        }
    }

    /**
     * @param list<Model|null> $items
     * @return list<array<string, mixed>>
     */
    protected function breadcrumbs(array $items, ?Model $context): array {
        $menu = app(AdminPermission::class)->getCurrentMenu();

        if ($menu === null) {
            return [];
        }

        $menus = app(Menus::class);
        $breadcrumbs = [];

        while ($menu !== null) {
            if ($menu->tag === null) {
                $breadcrumbs[] = ['title' => i18n($menu->token())];
            } else {
                $rendered = $this->render($menu->path, $context);
                $found = array_get_value($items, count($breadcrumbs));
                $context = $found instanceof Model ? $found : null;

                $breadcrumbs[] = [
                    'label' => $context === null ? null : $this->subject->title($context),
                    'path' => $rendered,
                    'title' => i18n($menu->token())
                ];

                $next = array_get_value($items, count($breadcrumbs));

                if ($menu->group && $next instanceof Model && $this->subject->recursive($this->model)) {
                    continue;
                }
            }

            $parent = $menu->parent;
            $menu = $parent === null ? null : $menus->node($parent);
        }

        return array_reverse($breadcrumbs);
    }

    /**
     * @return HasOneOrMany<Model, Model, *>
     */
    protected function cascading(Model $model, string $name): HasOneOrMany {
        $relation = $model->isRelation($name) ? $model->{$name}() : null;

        if (!$relation instanceof HasOneOrMany) {
            error('invalid-cascade-relation');
        }

        return $relation;
    }

    /**
     * @return Builder<Model>
     */
    protected function complete(): Builder {
        return $this->prepared($this->plan()->complete());
    }

    protected function compositeResolved(Column $column, mixed $value, mixed $input, ?Model $model): mixed {
        if (!is_array($value) || $column->variantGroup === null) {
            return $value;
        }

        $variant = $this->variant($column->variantGroup, $input, $model);

        if ($variant === null) {
            return $value;
        }

        foreach ($variant->definitions() as $field => $definition) {
            if (!in_array($definition->presentation, [Presentation::DriveFile, Presentation::DriveImage], true)) {
                continue;
            }

            if ($definition->translatable) {
                foreach (locales() as $locale) {
                    if (isset($value[$field][$locale]) && is_array($value[$field][$locale])) {
                        $value[$field][$locale] = app(FileService::class)->resolveDriveReferences($this->driveEntries($value[$field][$locale]), actor()->requireUser());
                    }
                }
            } elseif (isset($value[$field]) && is_array($value[$field])) {
                $value[$field] = app(FileService::class)->resolveDriveReferences($this->driveEntries($value[$field]), actor()->requireUser());
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<Column> $columns
     * @return array<string, mixed>
     */
    protected function dated(array $data, Model $record, array $columns): array {
        foreach ($columns as $column) {
            if ($column->type === ColumnType::Date) {
                $value = $record->getAttribute($column->name);

                if ($value instanceof DateTimeInterface) {
                    $data[$column->name] = $value->format(config('matrix.date-format'));
                }
            }
        }

        return $data;
    }

    /**
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    protected function driveEntries(array $value): array {
        $entries = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalized = [];

            foreach ($entry as $key => $item) {
                $normalized[(string) $key] = $item;
            }

            $entries[] = $normalized;
        }

        return $entries;
    }

    protected function driveResolved(Column $column, mixed $value): mixed {
        if (!in_array($column->presentation, [Presentation::DriveFile, Presentation::DriveImage], true) || !is_array($value)) {
            return $value;
        }

        return app(FileService::class)->resolveDriveReferences($this->driveEntries($value), actor()->requireUser());
    }

    protected function foreign(): ?string {
        return $this->standalone ? null : $this->subject->foreign($this->model);
    }

    /**
     * @param array<string, mixed>|Model|null $context
     */
    protected function inspect(Model $model, array|Model|null $context = null): void {
        foreach ($this->guards as $guard) {
            $guard($model, $context);
        }
    }

    /**
     * @template TModel of Model
     * @param Collection<int, TModel> $models
     * @return array<string, TModel>
     */
    protected function keyed(Collection $models): array {
        $keyed = [];

        foreach ($models as $model) {
            $keyed[strval($model->getKey())] = $model;
        }

        return $keyed;
    }

    /**
     * @return list<Column>
     */
    protected function local(): array {
        return array_values(array_filter($this->columns, fn (Column $column): bool => $this->isLocal($column)));
    }

    /**
     * @param list<Column> $columns
     * @return list<string>
     */
    protected function names(array $columns): array {
        $names = [];

        foreach ($columns as $column) {
            $names[] = $column->name;

            if ($column->translatable) {
                array_push($names, ...$this->translated($column));
            }
        }

        return $names;
    }

    /**
     * @param list<Operation> $operations
     * @return list<array<string, mixed>>
     */
    protected function normalized(array $operations, string $prefix): array {
        return array_map(fn (Operation $operation): array => $this->normalize($operation->type, $prefix), $operations);
    }

    /**
     * @param list<Operation> $operations
     * @return list<array<string, mixed>>
     */
    protected function operations(array $operations, string $prefix): array {
        return $this->normalized($this->permitted($operations, $prefix), $prefix);
    }

    /**
     * @param list<string|Operation> $operations
     * @return list<Operation>
     */
    protected function passing(array $operations, Model $record): array {
        return array_values(array_filter($this->wrap($operations), fn (Operation $operation): bool => $operation->when === null || ($operation->when)($record) === true));
    }

    /**
     * @param list<Column> $columns
     * @return list<array<string, mixed>>
     */
    protected function payload(array $columns, ?Model $record, mixed $input): array {
        return array_map(fn (Column $column): array => [
            ...$this->shape($column),
            'group' => $column->group,
            'op' => $column->op,
            'options' => $column->options === null ? null : $column->options->options($record),
            'path' => $column->path,
            'placeholder' => $column->placeholder,
            'remark' => $column->remark,
            'readonly' => $column->readonly,
            'required' => $column->required,
            'rule' => $column->rule,
            'sortable' => $column->sortable,
            'variant' => $this->resolvedVariant($column, $input, $record),
            'writable' => $this->writable($column)
        ], $columns);
    }

    /**
     * @param list<string|Operation> $operations
     * @return list<Operation>
     */
    protected function permitted(array $operations, string $prefix): array {
        return array_values(array_filter($this->wrap($operations), fn (Operation $operation): bool => $this->allowed($operation->type, $prefix)));
    }

    /**
     * @return Builder<BaseModel>
     */
    protected function plain(): Builder {
        return $this->prepared($this->model->query());
    }

    protected function plan(): QueryPlan {
        if ($this->plan === null) {
            $this->plan = new QueryPlan($this->model, $this->columns, $this->foreign());
        }

        return $this->plan;
    }

    protected function prefix(): string {
        if ($this->standalone) {
            return $this->subject->alias($this->model);
        }

        $derived = $this->subject->prefix($this->model);

        if (!$this->subject->recursive($this->model)) {
            return $derived;
        }

        $mounted = $this->mounted();

        return $mounted === null ? $derived : $mounted;
    }

    /**
     * @return Builder<Model>
     */
    protected function projection(): Builder {
        return $this->prepared($this->plan()->projection());
    }

    /**
     * @param array<string, Model> $models
     * @param list<string> $order
     * @return list<int>
     */
    protected function reassignRankings(array $models, string $field, array $order): array {
        return Ranking::reassign(array_map(fn (string $id): int => intval($models[$id]->getAttribute($field)), $order));
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    protected function resolvedVariant(Column $column, mixed $input, ?Model $model): ?array {
        if ($column->variantGroup === null) {
            return null;
        }

        $variant = $this->variant($column->variantGroup, $input, $model);

        return $variant === null ? null : $this->variantShape($column->variantGroup, $variant->definitions());
    }

    /**
     * @return array<string, list<string|Unique>>
     */
    protected function rules(mixed $input, ?Model $model, int|string|null $ignoreId = null): array {
        $rules = [];

        foreach ($this->local() as $column) {
            if (!$this->writable($column)) {
                continue;
            }

            if ($column->variantGroup !== null) {
                $this->expand($rules, $column->name, $column->variantGroup, $input, $model);

                continue;
            }

            $rule = $column->rule === [] ? [$column->type->rule()] : $column->rule;
            $prefix = $column->required ? ['required'] : ['present', 'nullable'];
            $keys = $column->translatable ? $this->translated($column) : [$column->name];

            foreach ($keys as $key) {
                $rules[$key] = $column->unique ? [...$prefix, ...$rule, $this->uniqueRule($key, $ignoreId)] : [...$prefix, ...$rule];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shape(Column $column): array {
        return [
            'name' => $column->name,
            'title' => $column->title,
            'translatable' => $column->translatable,
            'type' => $column->type->value,
            'format' => match ($column->type) {
                ColumnType::Date => $this->frontendFormat(config('matrix.date-format')),
                ColumnType::DateTime => $this->frontendFormat(config('matrix.datetime-format')),
                default => null
            },
            'presentation' => $column->presentation instanceof Presentation ? $column->presentation->value : $column->presentation
        ];
    }

    /**
     * @param list<Model> $parents
     */
    protected function subtitle(array $parents): ?string {
        foreach ($parents as $parent) {
            $title = $this->subject->title($parent);

            if (!blank($title)) {
                return $title;
            }
        }

        return null;
    }

    protected function title(): ?string {
        $menu = app(AdminPermission::class)->getCurrentMenu();

        return $menu === null ? null : i18n($menu->token());
    }

    /**
     * @param list<string|Operation> $actions
     * @return list<string|Operation>
     */
    protected function traceable(array $actions): array {
        if (!$this->model::TRACEABLE) {
            return $actions;
        }

        foreach ($actions as $action) {
            if ($action === 'log' || ($action instanceof Operation && $action->type === 'log')) {
                return $actions;
            }
        }

        return [...$actions, 'log'];
    }

    /**
     * @param list<string>|null $locales
     * @return list<string>
     */
    protected function translated(Column $column, ?array $locales = null): array {
        return array_map(fn (string $locale): string => "{$column->name}__{$locale}", $locales === null ? locales() : $locales);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(mixed $input, ?Model $model, int|string|null $ignoreId = null): array {
        $values = is_array($input) ? $input : [];

        return Validator::make($values, $this->rules($input, $model, $ignoreId))->validate();
    }

    /**
     * @param array<string, Definition> $definitions
     * @return list<array<string, mixed>>
     */
    protected function variantShape(string $group, array $definitions): array {
        $found = app(Resources::class)->getI18nBundle("model/{$group}");
        $bundle = $found === null ? [] : $found;
        $shapes = [];

        foreach ($definitions as $name => $definition) {
            if ($definition->presentation === Presentation::Composite) {
                error('nested-composite-not-supported');
            }

            $title = array_get_value($bundle, $name);

            $shapes[] = [
                'name' => $name,
                'title' => is_string($title) ? $title : "{{$name}}",
                'translatable' => $definition->translatable,
                'type' => $definition->type->value,
                'presentation' => $definition->presentation instanceof Presentation ? $definition->presentation->value : $definition->presentation,
                'options' => $definition->options === null ? null : (is_string($definition->options) ? app($definition->options) : $definition->options)->options(),
                'required' => $definition->required,
                'rule' => $definition->rule instanceof Closure ? ($definition->rule)() : $definition->rule
            ];
        }

        return $shapes;
    }

    /**
     * @param list<string|Operation> $operations
     * @return list<Operation>
     */
    protected function wrap(array $operations): array {
        return array_map(fn (string|Operation $operation): Operation => $operation instanceof Operation ? $operation : new Operation($operation), $operations);
    }

    protected function writable(Column $column): bool {
        return $this->isLocal($column) && !$column->readonly;
    }

    private function allowed(string $type, string $prefix): bool {
        $url = $this->resolvedUrl(app(Actions::class)->define($type), $prefix);

        return $url !== null && app(AdminPermission::class)->reaches($url);
    }

    /**
     * @param array<string, list<string|Unique>> $rules
     */
    private function expand(array &$rules, string $name, string $group, mixed $input, ?Model $model): void {
        $variant = $this->variant($group, $input, $model);

        if ($variant === null) {
            return;
        }

        foreach ($variant->definitions() as $field => $definition) {
            $rule = $definition->rule instanceof Closure ? ($definition->rule)() : $definition->rule;
            $rule = $rule === [] ? [$definition->type->rule()] : $rule;
            $prefix = $definition->required ? ['required'] : ['present', 'nullable'];

            if ($definition->translatable) {
                foreach (locales() as $locale) {
                    $rules["{$name}.{$field}.{$locale}"] = [...$prefix, ...$rule];
                }
            } else {
                $rules["{$name}.{$field}"] = [...$prefix, ...$rule];
            }
        }
    }

    private function frontendFormat(string $format): string {
        return strtr($format, [
            'Y' => 'YYYY', 'y' => 'YY',
            'm' => 'MM', 'n' => 'M',
            'd' => 'DD', 'j' => 'D',
            'H' => 'HH', 'G' => 'H',
            'h' => 'hh', 'g' => 'h',
            'i' => 'mm', 's' => 'ss',
            'A' => 'A', 'a' => 'a'
        ]);
    }

    private function has(string $name): bool {
        return in_array($name, array_column($this->columns, 'name'), true);
    }

    private function isLocal(Column $column): bool {
        return !$column->virtual && $column->expression->path === [];
    }

    private function mounted(): ?string {
        $menus = app(Menus::class);
        $menu = app(AdminPermission::class)->getCurrentMenu();

        while ($menu !== null && !$menu->group) {
            $menu = $menus->above($menu);
        }

        return $menu?->path;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(string $type, string $prefix): array {
        $action = app(Actions::class)->define($type);
        $url = $this->resolvedUrl($action, $prefix);

        if ($url !== null) {
            $action['url'] = $url;
        }

        return $action;
    }

    private function owner(string $foreign): mixed {
        $value = array_get_value($this->params, $foreign);

        if ($value === null) {
            error('data-not-found', 404);
        }

        return $value;
    }

    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    private function prepared(Builder $query): Builder {
        $foreign = $this->foreign();

        if ($foreign !== null) {
            $query->where("{$this->model->getTable()}.{$foreign}", $this->owner($foreign));
        }

        foreach ($this->scopes as $scope) {
            $scope($query);
        }

        return $query;
    }

    private function render(string $template, ?Model $context): string {
        $replaced = preg_replace_callback('/\{(\w+)\}/u', function (array $matches) use ($context): string {
            $value = $context === null ? null : $context->getAttribute($matches[1]);

            return is_scalar($value) ? strval($value) : $matches[0];
        }, $template);

        return is_string($replaced) ? $replaced : $template;
    }

    /**
     * @param string|array<string, mixed> $column
     */
    private function resolve(string|array $column): Column {
        return $this->resolver->resolve((new ColumnParser())->parse($column), $this->model);
    }

    /**
     * @param array<string, mixed> $action
     */
    private function resolvedUrl(array $action, string $prefix): ?string {
        $url = array_get_value($action, 'url');

        return is_string($url) ? str_replace('{prefix}', $prefix, $url) : null;
    }

    private function uniqueRule(string $field, int|string|null $ignoreId): Unique {
        $rule = Rule::unique($this->model->getTable(), $field);

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }

    private function variant(string $group, mixed $input, ?Model $model): ?Variant {
        $resolver = resolve_driver($group, TypeResolver::class, 'invalid-type-resolver');
        $type = $resolver?->resolve($model, $input);
        $class = is_string($type) ? cfg("{$group}.{$type}") : null;

        if (!is_string($class) || !is_a($class, Variant::class, true)) {
            return null;
        }

        $instance = app($class);

        return $instance instanceof Variant ? $instance : null;
    }

}
