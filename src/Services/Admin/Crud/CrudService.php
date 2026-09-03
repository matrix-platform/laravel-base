<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Support\Actions;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
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

    protected Model $model;

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
     * @param class-string<Model> $model
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
     * @param Collection<int, Model> $models
     * @return array<string, Model>
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
    protected function payload(array $columns, ?Model $record): array {
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
     * @return Builder<Model>
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
        return $this->standalone ? $this->subject->alias($this->model) : $this->subject->prefix($this->model);
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
     * @return array<string, list<string|Unique>>
     */
    protected function rules(int|string|null $ignoreId = null): array {
        $rules = [];

        foreach ($this->local() as $column) {
            if (!$this->writable($column)) {
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
     * @param list<string>|null $locales
     * @return list<string>
     */
    protected function translated(Column $column, ?array $locales = null): array {
        return array_map(fn (string $locale): string => "{$column->name}__{$locale}", $locales === null ? locales() : $locales);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(mixed $input, int|string|null $ignoreId = null): array {
        $values = is_array($input) ? $input : [];

        return Validator::make($values, $this->rules($ignoreId))->validate();
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

    private function has(string $name): bool {
        return in_array($name, array_column($this->columns, 'name'), true);
    }

    private function isLocal(Column $column): bool {
        return !$column->virtual && $column->expression->path === [];
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
     * @param Builder<Model> $query
     * @return Builder<Model>
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

}
