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
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Declarations\Presets;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\Filtering;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Columns\Syntax\Expression;
use MatrixPlatform\Columns\Syntax\ParsedColumn;
use MatrixPlatform\Models\BaseModel;
use MatrixPlatform\Services\FileService;
use MatrixPlatform\Services\PreferenceService;
use MatrixPlatform\Support\Actions;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Resources;
use MatrixPlatform\Support\ScheduleFilter;
use MatrixPlatform\Support\Subject;
use MatrixPlatform\Support\Variants;

abstract class CrudService {

    private const FLOAT_RANGE = '-1.7976931348623157e308,1.7976931348623157e308';

    private const INTEGER_RANGE = '-2147483648,2147483647';

    /**
     * @var list<Column>
     */
    protected array $columns = [];

    protected BaseModel $model;

    /**
     * @var list<string>
     */
    protected array $orderable = [];

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * @var list<string>
     */
    protected array $selects = [];

    /**
     * @var list<array{name: string, title: ?string}>
     */
    protected array $staged = [];

    protected Subject $subject;

    /**
     * @var array<string, Closure>
     */
    private array $filterHandlers = [];

    /**
     * @var list<Closure>
     */
    private array $guards = [];

    private ?string $mount = null;

    private ColumnResolver $resolver;

    /**
     * @var list<Closure>
     */
    private array $scopes = [];

    private bool $standalone = false;

    private Variants $variants;

    private bool $verified = false;

    /**
     * @param class-string<BaseModel> $model
     */
    public function __construct(string $model) {
        $this->model = new $model();
        $this->resolver = app(ColumnResolver::class);
        $this->subject = app(Subject::class);
        $this->variants = app(Variants::class);
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    public function columns(array $columns): static {
        foreach ($this->resolved($columns) as $column) {
            if (!$this->has($column->name)) {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    public function filterUsing(string $name, Closure $apply): static {
        $this->filterHandlers[$name] = $apply;

        return $this;
    }

    public function guard(Closure $guard): static {
        $this->guards[] = $guard;

        return $this;
    }

    public function mount(?string $prefix): static {
        $this->mount = $prefix;

        return $this;
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    public function orderable(array $columns): static {
        $this->orderable = array_map(fn (string|array $column): string => $this->resolve($column)->name, $columns);

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function params(array $params): static {
        $this->params = $params;
        $this->verified = false;

        return $this;
    }

    public function scope(Closure $scope): static {
        $this->scopes[] = $scope;

        return $this;
    }

    /**
     * @param list<string> $selects
     */
    public function selects(array $selects): static {
        $this->selects = $selects;

        return $this;
    }

    /**
     * @param list<array{name: string, title: ?string}> $lists
     */
    public function staged(array $lists): static {
        $this->staged = $lists;

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
    protected function assign(Form $form, Model $model, array $values): void {
        foreach ($this->local($form->columns) as $column) {
            if ($column->readonly || $form->composed($column)) {
                continue;
            }

            if ($column->translatable) {
                $this->assignTranslated($model, $column, $values);

                continue;
            }

            if (array_key_exists($column->name, $values)) {
                $model->setAttribute($column->name, $this->stored($column, $this->driveResolved($column, $values[$column->name])));
            }
        }

        $this->assignComposites($form, $model, $values);

        foreach ($form->columns as $column) {
            if ($column->virtual && $column->assign !== null && $this->writable($column) && array_key_exists($column->name, $values)) {
                ($column->assign)($model, $values[$column->name]);
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

            $menu = $menus->above($menu);
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
     * @param array<string, mixed> $preference
     */
    protected function columnPreference(array $preference): mixed {
        return array_get_value($preference, "column:{$this->subject->key($this->prefix())}");
    }

    /**
     * @return Builder<Model>
     */
    protected function complete(QueryPlan $plan): Builder {
        return $this->prepared($plan->complete());
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

    protected function expandComposites(mixed $input, ?Model $model): Form {
        $expanded = [];
        $composites = [];
        $presets = [];

        foreach ($this->columns as $column) {
            if ($column->variantGroup === null) {
                $expanded[] = $column;

                continue;
            }

            $type = $this->variants->type($column->variantGroup, $model, $input);
            $variant = $this->variants->of($column->variantGroup, $type);

            if ($variant === null) {
                continue;
            }

            if ($variant instanceof Presets) {
                $presets[$column->name] = $variant->defaults();
            }

            foreach ($this->subfields($column, $column->variantGroup, $type, $variant->definitions()) as $field => $subfield) {
                $composites[$column->name][$field] = $subfield;
                $expanded[] = $subfield;
            }
        }

        return new Form($expanded, $composites, $presets);
    }

    /**
     * @param list<Column> $columns
     * @param list<string>|null $locales
     * @return list<array{name: string, column: Column, title: string}>
     */
    protected function fields(array $columns, ?array $locales): array {
        $fields = [];

        foreach ($columns as $column) {
            if ($column->translatable && $locales !== null) {
                foreach (array_combine($locales, $this->translated($column, $locales)) as $locale => $key) {
                    $fields[] = ['name' => $key, 'column' => $column, 'title' => $this->localized($column->title, $locale)];
                }

                continue;
            }

            $fields[] = ['name' => $column->name, 'column' => $column, 'title' => $column->title];
        }

        return $fields;
    }

    /**
     * @param Builder<Model> $query
     */
    protected function filter(Builder $query, QueryPlan $plan, mixed $filters): void {
        $metadata = app(MetadataRegistry::class)->of($this->model::class);

        if (is_array($filters) && $metadata?->enable !== null && $metadata->disable !== null && array_key_exists(ScheduleFilter::NAME, $filters)) {
            $this->schedule($query, $filters[ScheduleFilter::NAME], $metadata->enable, $metadata->disable);

            unset($filters[ScheduleFilter::NAME]);
        }

        (new Filtering())->apply($query, $plan, $filters, $this->filterHandlers);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function flattenComposites(Form $form, array $data, Model $model): array {
        foreach ($form->composites as $group => $columns) {
            $stored = $model->getAttribute($group);
            $stored = is_array($stored) ? $stored : [];

            foreach ($columns as $field => $column) {
                $value = array_get_value($stored, $field);

                if (!$column->translatable) {
                    $data[$column->name] = $value;

                    continue;
                }

                foreach (array_combine(locales(), $this->translated($column)) as $locale => $key) {
                    $data[$key] = is_array($value) ? array_get_value($value, $locale) : null;
                }
            }
        }

        return $data;
    }

    protected function foreign(): ?string {
        return $this->standalone ? null : $this->subject->foreign($this->model);
    }

    /**
     * @param array<string, mixed> $preference
     */
    protected function formPreference(array $preference): mixed {
        return array_get_value($preference, "column:{$this->subject->key($this->prefix())}-edit");
    }

    protected function heading(): string {
        $title = $this->title();

        return $title === null ? $this->subject->alias($this->model) : $title;
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
     * @param list<Column> $columns
     * @return list<Column>
     */
    protected function local(array $columns): array {
        return array_values(array_filter($columns, $this->isLocal(...)));
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
    protected function payload(array $columns, ?Model $record): array {
        return array_map(fn (Column $column): array => [
            ...$this->shape($column),
            ...$this->driveProps($column),
            'group' => $column->group,
            'hint' => $column->hint,
            'locked' => $column->locked,
            'op' => $column->op,
            'options' => $column->options?->options($record),
            'path' => $column->path,
            'placeholder' => $column->placeholder,
            'props' => $column->props,
            'remark' => $column->remark,
            'readonly' => $column->readonly,
            'required' => $column->required,
            'rule' => $column->rule,
            'section' => $column->section,
            'sortable' => $column->sortable,
            'writable' => $this->writable($column) && !$column->locked
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

    /**
     * @param list<Column> $columns
     */
    protected function plan(array $columns): QueryPlan {
        return new QueryPlan($this->model, $columns, $this->foreign());
    }

    /**
     * @param list<Column> $columns
     * @param list<string|array<string, mixed>> $optionals
     */
    protected function pool(array $columns, array $optionals): Pool {
        $defaults = array_column($columns, null, 'name');
        $replacements = $this->replacements($defaults);
        $foreign = $this->foreign();
        $placed = [];
        $pooled = [];
        $replaced = [];

        foreach ($optionals as $optional) {
            $parsed = (new ColumnParser())->parse($optional);
            $name = $this->replacementName($parsed, $defaults, $replacements);

            if ($name !== $parsed->name && !array_key_exists($name, $placed)) {
                $replaced[$name] = strval($parsed->expression->field);
            }

            if (array_key_exists($name, $defaults)) {
                $placed[$name] = true;

                continue;
            }

            if ($foreign !== null && $parsed->expression->field === $foreign && $parsed->expression->path === [] && $parsed->expression->aggregate === null) {
                continue;
            }

            $column = $this->resolver->resolve($parsed, $this->model);

            if (!array_key_exists($name, $placed) && $this->listable($parsed, $column)) {
                $placed[$name] = true;
                $pooled[] = $column;
            }
        }

        return new Pool([...array_values($defaults), ...$pooled], $replaced);
    }

    /**
     * @return array<string, mixed>
     */
    protected function preference(): array {
        $identity = actor()->current();

        return $identity === null ? [] : app(PreferenceService::class)->get($identity);
    }

    protected function prefix(): string {
        if ($this->mount !== null) {
            return $this->mount;
        }

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
    protected function projection(QueryPlan $plan): Builder {
        return $this->prepared($plan->projection());
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
     * @param list<string|array<string, mixed>> $specs
     * @return list<Column>
     */
    protected function resolved(array $specs): array {
        $resolved = [];

        foreach ($specs as $spec) {
            $column = $this->resolve($spec);

            if (!array_key_exists($column->name, $resolved)) {
                $resolved[$column->name] = $column;
            }
        }

        return array_values($resolved);
    }

    /**
     * @return array<string, list<string|Unique>>
     */
    protected function rules(Form $form, int|string|null $ignoreId = null): array {
        $rules = [];

        foreach ($form->columns as $column) {
            if (!$this->writable($column)) {
                continue;
            }

            $rule = $column->rule === [] ? [$column->type->rule()] : $column->rule;
            $rule = $form->composed($column) ? $rule : [...$rule, ...$this->range($column, $rule)];
            $prefix = $column->required ? ['required'] : ($column->virtual ? ['nullable'] : ['present', 'nullable']);
            $keys = $column->translatable ? $this->translated($column) : [$column->name];

            foreach ($keys as $key) {
                $rules[$key] = $column->unique ? [...$prefix, ...$rule, $this->uniqueRule($key, $ignoreId)] : [...$prefix, ...$rule];
            }
        }

        return $rules;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    protected function scalars(array $values): bool {
        return array_filter($values, fn (mixed $value): bool => !is_int($value) && !is_string($value)) === [];
    }

    /**
     * @param Builder<Model> $query
     * @param list<string> $fields
     */
    protected function select(Builder $query, QueryPlan $plan, array $fields): void {
        $present = array_column($plan->columns(), 'name');
        $missing = array_values(array_diff(array_unique($fields), $present));

        if ($missing !== []) {
            $query->addSelect(array_map(fn (string $field): string => "{$plan->table()}.{$field}", $missing));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function shape(Column $column): array {
        $format = $column->type->format();

        return [
            'name' => $column->name,
            'title' => $column->title,
            'translatable' => $column->translatable,
            'type' => $column->type->value,
            'format' => $format === null ? null : $this->frontendFormat($format),
            'presentation' => $column->presentation instanceof Presentation ? $column->presentation->value : $column->presentation
        ];
    }

    protected function store(Form $form, Model $model, mixed $input): void {
        $this->assign($form, $model, $this->validated($form, $input));
        $this->attach($model);
        $this->inspect($model);

        $model->save();
    }

    /**
     * @param array<string, Definition> $definitions
     * @return array<string, Column>
     */
    protected function subfields(Column $composite, string $group, ?string $type, array $definitions): array {
        $bundle = $this->bundle($group, $type);
        $columns = [];

        foreach ($definitions as $field => $definition) {
            if ($definition->presentation === Presentation::Composite) {
                error('nested-composite-not-supported');
            }

            $name = "{$composite->name}__{$field}";
            $options = $definition->options === null ? null : (is_string($definition->options) ? app($definition->options) : $definition->options);
            $presentation = $definition->presentation;
            $title = $this->label($bundle, $field);

            $columns[$field] = new Column(
                expression: new Expression(null, [], $name, null, []),
                group: $definition->group,
                hint: $this->label($bundle, "{$field}:hint"),
                name: $name,
                locked: false,
                op: null,
                options: $options,
                path: null,
                placeholder: $this->label($bundle, "{$field}:placeholder"),
                presentation: $presentation === null ? ($options === null ? Presentation::Plain : Presentation::Select) : $presentation,
                readonly: $composite->readonly,
                remark: $this->label($bundle, "{$field}:remark"),
                required: $definition->required,
                rule: $definition->rule instanceof Closure ? ($definition->rule)() : $definition->rule,
                sortable: false,
                section: $definition->section === null ? $composite->section : $definition->section,
                title: $title === null ? "{{$field}}" : $title,
                translatable: $definition->translatable,
                type: $definition->type,
                unique: false,
                variantGroup: null,
                virtual: false,
                props: $definition->props
            );
        }

        return $columns;
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
     * @return array<string, mixed>
     */
    protected function validated(Form $form, mixed $input, int|string|null $ignoreId = null): array {
        $values = is_array($input) ? $input : [];

        return Validator::make($values, $this->rules($form, $ignoreId))->validate();
    }

    protected function writable(Column $column): bool {
        return $this->rooted($column) && !$column->readonly;
    }

    private function allowed(string $type, string $prefix): bool {
        $url = $this->resolvedUrl(app(Actions::class)->define($type), $prefix);

        return $url !== null && app(AdminPermission::class)->reaches($url);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function assignComposites(Form $form, Model $model, array $values): void {
        foreach ($form->composites as $group => $columns) {
            if (array_filter($columns, fn (Column $column): bool => $column->readonly) !== []) {
                continue;
            }

            $data = [];

            foreach ($columns as $field => $column) {
                if (!$column->translatable) {
                    if (array_key_exists($column->name, $values)) {
                        $data[$field] = $this->driveResolved($column, $values[$column->name]);
                    }

                    continue;
                }

                $translated = [];

                foreach (array_combine(locales(), $this->translated($column)) as $locale => $key) {
                    if (array_key_exists($key, $values)) {
                        $translated[$locale] = $this->driveResolved($column, $values[$key]);
                    }
                }

                $data[$field] = $translated;
            }

            $model->setAttribute($group, $data);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function assignTranslated(Model $model, Column $column, array $values): void {
        foreach ($this->translated($column) as $key) {
            if (array_key_exists($key, $values)) {
                $model->setAttribute($key, $this->driveResolved($column, $values[$key]));
            }
        }
    }

    private function auditing(Column $column): bool {
        return $this->rooted($column) && array_key_exists(strval($column->expression->field), Definitions::auditings());
    }

    /**
     * @return array<string, mixed>
     */
    private function bundle(string $group, ?string $type): array {
        $resources = app(Resources::class);
        $shared = $resources->getI18nBundle("model/{$group}");
        $specific = $type === null ? null : $resources->getI18nBundle("model/{$group}-{$type}");

        return array_replace($shared === null ? [] : $shared, $specific === null ? [] : $specific);
    }

    private function declared(ParsedColumn $parsed, Column $column): bool {
        if ($parsed->presentation !== null) {
            return true;
        }

        $definitions = $this->rooted($column) ? app(MetadataRegistry::class)->definitions($this->model::class) : null;
        $definition = $definitions === null ? null : array_get_value($definitions, strval($column->expression->field));

        return $definition instanceof Definition && $definition->presentation !== null;
    }

    /**
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    private function driveEntries(array $value): array {
        /** @var list<array<string, mixed>> $entries */
        $entries = array_values(array_filter($value, is_array(...)));

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function driveProps(Column $column): array {
        if (!$this->drives($column)) {
            return [];
        }

        $multiple = in_array('array', $column->rule, true);
        $max = null;

        foreach ($multiple ? $column->rule : [] as $rule) {
            if (str_starts_with($rule, 'max:')) {
                $max = (int) substr($rule, 4);
            }
        }

        return ['max' => $max, 'multiple' => $multiple];
    }

    private function driveResolved(Column $column, mixed $value): mixed {
        if (!$this->drives($column) || !is_array($value)) {
            return $value;
        }

        return app(FileService::class)->resolveDriveReferences($this->driveEntries($value), actor()->requireUser());
    }

    private function drives(Column $column): bool {
        return in_array($column->presentation, [Presentation::DriveFile, Presentation::DriveImage], true);
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
        return !$column->virtual && $this->rooted($column);
    }

    /**
     * @param array<string, mixed> $bundle
     */
    private function label(array $bundle, string $token): ?string {
        $found = array_get_value($bundle, $token);

        return is_string($found) ? $found : null;
    }

    private function listable(ParsedColumn $parsed, Column $column): bool {
        if (($column->virtual && $column->op === null) || ($column->section !== null && !$this->auditing($column)) || is_string($column->presentation) || in_array($column->presentation, [Presentation::Composite, Presentation::Hidden, Presentation::Password], true)) {
            return false;
        }

        if ($this->rooted($column) && in_array($column->expression->field, $this->model->getHidden(), true)) {
            return false;
        }

        return $column->type !== ColumnType::Json || $column->presentation !== Presentation::Plain || $this->declared($parsed, $column);
    }

    private function localized(string $title, string $locale): string {
        return strtr(i18n('backend.export.locale-column'), [':title' => $title, ':locale' => i18n("backend.locale-{$locale}")]);
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

        if (!$this->verified) {
            $this->verifyLineage();

            $this->verified = true;
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

    /**
     * @param list<mixed> $rule
     * @return list<string>
     */
    private function range(Column $column, array $rule): array {
        $range = match ($column->type) {
            ColumnType::Float => self::FLOAT_RANGE,
            ColumnType::Integer => self::INTEGER_RANGE,
            default => null
        };

        $declared = [];

        foreach ($rule as $item) {
            if (is_string($item) && preg_match_all('/(?:\A|\|)(min|max|between):/', $item, $matches) > 0) {
                $declared = [...$declared, ...$matches[1]];
            }
        }

        if (in_array('between', $declared, true) || (in_array('min', $declared, true) && in_array('max', $declared, true))) {
            return [];
        }

        return $range === null ? [] : ["between:{$range}"];
    }

    private function render(string $template, ?Model $context): string {
        $replaced = preg_replace_callback('/\{(\w+)\}/u', function (array $matches) use ($context): string {
            $value = $context?->getAttribute($matches[1]);

            if (!is_scalar($value)) {
                $value = array_get_value($this->params, $matches[1]);
            }

            return is_scalar($value) ? strval($value) : $matches[0];
        }, $template);

        return is_string($replaced) ? $replaced : $template;
    }

    /**
     * @param array<string, Column> $defaults
     * @param array<string, string> $replacements
     */
    private function replacementName(ParsedColumn $parsed, array $defaults, array $replacements): string {
        $expression = $parsed->expression;
        $field = strval($expression->field);
        $name = $parsed->name;

        if (!array_key_exists($name, $defaults) && $expression->path === [] && $expression->aggregate === null && array_key_exists($field, $replacements)) {
            $name = $replacements[$field];
        }

        return $name;
    }

    /**
     * @param array<string, Column> $columns
     * @return array<string, string>
     */
    private function replacements(array $columns): array {
        $replacements = [];

        foreach ($columns as $name => $column) {
            $expression = $column->expression;
            $relation = count($expression->path) === 1 && $expression->aggregate === null ? $this->subject->belongsTo($this->model, $expression->path[0]) : null;

            if ($relation !== null && !array_key_exists($relation->getForeignKeyName(), $replacements)) {
                $replacements[$relation->getForeignKeyName()] = $name;
            }
        }

        return $replacements;
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

    private function rooted(Column $column): bool {
        return $column->expression->path === [];
    }

    /**
     * @param Builder<Model> $query
     */
    private function schedule(Builder $query, mixed $filter, string $enable, string $disable): void {
        if (!is_array($filter) || array_get_value($filter, 'op') !== 'eq') {
            return;
        }

        $value = array_get_value($filter, 'value');

        if ($value === null || $value === '') {
            return;
        }

        $state = is_string($value) ? ScheduleFilter::tryFrom($value) : null;

        if ($state === null) {
            error('invalid-filter-value');
        }

        $state->apply($query, $enable, $disable, now());
    }

    private function stored(Column $column, mixed $value): mixed {
        return $column->type === ColumnType::Float && is_string($value) && is_numeric($value) ? floatval($value) : $value;
    }

    /**
     * @param list<string>|null $locales
     * @return list<string>
     */
    private function translated(Column $column, ?array $locales = null): array {
        return array_map(fn (string $locale): string => "{$column->name}__{$locale}", $locales === null ? locales() : $locales);
    }

    private function uniqueRule(string $field, int|string|null $ignoreId): Unique {
        $rule = Rule::unique($this->model->getTable(), $field);

        if (method_exists($this->model, 'getDeletedAtColumn')) {
            $rule->withoutTrashed($this->model->getDeletedAtColumn());
        }

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId, $this->model->getKeyName());
    }

    private function verifyLineage(): void {
        $model = $this->model;
        $child = null;
        $visited = [];

        while (!array_key_exists($model::class, $visited) && ($relation = $this->subject->parent($model)) !== null) {
            $visited[$model::class] = true;
            $foreign = $relation->getForeignKeyName();
            $value = array_get_value($this->params, $foreign);

            if (!is_scalar($value)) {
                return;
            }

            $owned = $child?->getAttribute($foreign);

            if ($child !== null && (!is_scalar($owned) || strval($owned) !== strval($value))) {
                error('data-not-found', 404);
            }

            $related = $relation->getRelated();
            $query = $related->newQuery();
            $parent = ($relation->getOwnerKeyName() === $related->getKeyName() ? $query->whereKey($value) : $query->where($relation->getQualifiedOwnerKeyName(), $value))->first();

            if ($parent === null) {
                error('data-not-found', 404);
            }

            $model = $child = $parent;
        }
    }

    /**
     * @param list<string|Operation> $operations
     * @return list<Operation>
     */
    private function wrap(array $operations): array {
        return array_map(fn (string|Operation $operation): Operation => $operation instanceof Operation ? $operation : new Operation($operation), $operations);
    }

}
