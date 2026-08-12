<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\Validator;
use MatrixPlatform\Columns\Column;
use MatrixPlatform\Columns\ColumnResolver;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Columns\Query\QueryPlan;
use MatrixPlatform\Columns\Syntax\ColumnParser;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\Subject;

abstract class CrudService {

    /**
     * @var list<Column>
     */
    protected array $columns = [];

    protected ?Closure $guard = null;

    protected Model $model;

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    private ?QueryPlan $plan = null;

    private ColumnResolver $resolver;

    /**
     * @var list<Closure>
     */
    protected array $scopes = [];

    protected bool $standalone = false;

    protected Subject $subject;

    /**
     * @param class-string<Model> $model
     */
    public function __construct(string $model) {
        $this->model = new $model();
        $this->resolver = app(ColumnResolver::class);
        $this->subject = app(Subject::class);
        $this->columns = [$this->resolve(['name' => 'id', 'type' => 'hidden', 'readonly' => true, 'virtual' => true])];
    }

    /**
     * @param list<string|array<string, mixed>> $columns
     */
    public function columns(array $columns): static {
        foreach ($columns as $column) {
            $this->columns[] = $this->resolve($column);
        }

        $this->plan = null;

        return $this;
    }

    public function guard(Closure $guard): static {
        $this->guard = $guard;

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

    protected function attach(Model $model): void {
        $foreign = $this->foreign();

        if ($foreign !== null) {
            $model->setAttribute($foreign, $this->owner($foreign));
        }
    }

    /**
     * @param list<Model> $items
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

    /**
     * @param array<string, mixed>|Model|null $context
     */
    protected function inspect(Model $model, array|Model|null $context = null): void {
        if ($this->guard !== null) {
            ($this->guard)($model, $context);
        }
    }

    /**
     * @return list<Column>
     */
    protected function local(): array {
        return array_values(array_filter($this->columns, fn (Column $column): bool => !$column->virtual && $column->expression->path === []));
    }

    /**
     * @param list<Operation> $operations
     * @return list<array<string, mixed>>
     */
    protected function operations(array $operations, string $prefix): array {
        return array_map(fn (Operation $operation): array => $this->normalize($operation->type, $prefix), $operations);
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
            'name' => $column->name,
            'title' => $column->title,
            'type' => $column->type->value,
            'presentation' => $column->presentation instanceof Presentation ? $column->presentation->value : $column->presentation,
            'group' => $column->group,
            'op' => $column->op,
            'options' => $column->options === null ? null : $column->options->options($record),
            'path' => $column->path,
            'placeholder' => $column->placeholder,
            'remark' => $column->remark,
            'readonly' => $column->readonly,
            'required' => $column->required,
            'rule' => $column->rule,
            'sortable' => $column->sortable
        ], $columns);
    }

    /**
     * @return Builder<Model>
     */
    protected function plain(): Builder {
        return $this->prepared($this->model->query());
    }

    protected function plan(): QueryPlan {
        if ($this->plan === null) {
            $this->plan = new QueryPlan($this->model, $this->columns);
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
     * @return array<string, list<string>>
     */
    protected function rules(): array {
        $rules = [];

        foreach ($this->local() as $column) {
            if ($column->readonly) {
                continue;
            }

            $rule = $column->rule === [] ? [$column->type->rule()] : $column->rule;
            $prefix = $column->required ? ['required'] : ['present', 'nullable'];

            $rules[$column->name] = [...$prefix, ...$rule];
        }

        return $rules;
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
     * @return array<string, mixed>
     */
    protected function validated(mixed $input): array {
        $values = is_array($input) ? $input : [];

        Validator::make($values, $this->rules())->validate();

        return $values;
    }

    /**
     * @param list<string|Operation> $operations
     * @return list<Operation>
     */
    protected function wrap(array $operations): array {
        return array_map(fn (string|Operation $operation): Operation => $operation instanceof Operation ? $operation : new Operation($operation), $operations);
    }

    private function foreign(): ?string {
        return $this->standalone ? null : $this->subject->foreign($this->model);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(string $type, string $prefix): array {
        $configured = cfg("actions.{$type}", []);
        $action = is_array($configured) ? $configured : [];
        $confirm = array_get_value($action, 'confirm');
        $url = array_get_value($action, 'url');

        $action['type'] = $type;
        $action['title'] = i18n("actions.{$type}");

        if (is_string($confirm)) {
            $action['confirm'] = i18n($confirm);
        }

        if (is_string($url)) {
            $action['url'] = str_replace('{prefix}', $prefix, $url);
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

}
