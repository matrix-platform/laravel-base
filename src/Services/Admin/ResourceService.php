<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\Validator;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Support\Actions;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\ResourceGroup;
use MatrixPlatform\Support\Resources;

class ResourceService {

    private bool $pinned = false;

    private string $prefix = 'resource';

    public function __construct(private Resources $resources, private Actions $actions) {}

    /**
     * @return array<string, mixed>
     */
    public function get(ResourceGroup $group, string $name): array {
        $this->verify($group, $name);

        $id = $this->identify($group, $name);
        $relative = $this->relative($group, $name);
        $defaults = $this->defaults($relative);

        return $this->payload($id, $name, $relative, $defaults, $this->columns($id, $defaults));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(ResourceGroup $group, bool $unrestricted): array {
        $existing = $this->resources->bundleNames($group->directory());
        $names = $unrestricted ? $existing : array_values(array_intersect($this->allowed($group), $existing));
        $rows = [];

        foreach ($names as $name) {
            $rows[] = $this->row($group, $name);
        }

        return [
            'title' => $this->heading(),
            'breadcrumbs' => $this->breadcrumbs(),
            'columns' => [
                ['name' => 'name', 'title' => i18n('resource.column-name'), 'type' => 'text']
            ],
            'sorting' => [],
            'pagination' => ['page' => 1, 'size' => max(1, count($rows)), 'total' => count($rows)],
            'rows' => $rows,
            'actions' => [
                'page' => [],
                'row' => [$this->action('edit', '{id}')]
            ]
        ];
    }

    public function pinned(bool $pinned = true): static {
        $this->pinned = $pinned;

        return $this;
    }

    public function prefix(string $prefix): static {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(ResourceGroup $group, string $name, mixed $input): array {
        $this->verify($group, $name);

        $id = $this->identify($group, $name);
        $relative = $this->relative($group, $name);
        $defaults = $this->defaults($relative);
        $columns = $this->columns($id, $defaults);
        $values = is_array($input) ? $input : [];
        $writable = $this->writable($columns, $values);

        $this->validate(array_filter($writable, fn (string $key): bool => !$this->cleared($values[$key]), ARRAY_FILTER_USE_KEY), $values);

        $record = $this->locked($relative);
        $override = $record === null ? [] : $record->data;

        foreach ($writable as $key => $column) {
            $coerced = $this->cleared($values[$key]) ? null : $this->coerce($column, $values[$key]);

            if ($coerced === null || $coerced === array_get_value($defaults, $key)) {
                unset($override[$key]);
            } else {
                $override[$key] = $coerced;
            }
        }

        $this->save($relative, $record, $override);

        return $this->payload($id, $name, $relative, $defaults, $columns);
    }

    public function whitelisted(ResourceGroup $group, string $name): bool {
        return in_array($name, $this->allowed($group), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function action(string $type, string $path): array {
        $action = $this->actions->define($type);

        $action['url'] = "{$this->prefix}/{$path}";

        return $action;
    }

    /**
     * @return list<string>
     */
    private function allowed(ResourceGroup $group): array {
        $configured = config("matrix.{$group->config()}");

        return is_array($configured) ? array_map(strval(...), array_values($configured)) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function breadcrumbs(): array {
        $menu = app(AdminPermission::class)->getCurrentMenu();
        $menus = app(Menus::class);
        $crumbs = [];

        while ($menu !== null) {
            $crumbs[] = ['title' => i18n($menu->token()), 'path' => $menu->tag === null ? null : $menu->path];

            $parent = $menu->parent;
            $menu = $parent === null ? null : $menus->node($parent);
        }

        return array_reverse($crumbs);
    }

    private function cleared(mixed $value): bool {
        return $value === null || $value === '';
    }

    /**
     * @param array<string, mixed> $column
     */
    private function coerce(array $column, mixed $value): mixed {
        return match (array_get_value($column, 'type')) {
            ColumnType::Boolean->value => filter_var($value, FILTER_VALIDATE_BOOL),
            ColumnType::Float->value => floatval($value),
            ColumnType::Integer->value => intval($value),
            default => is_scalar($value) ? strval($value) : $value
        };
    }

    /**
     * @param array<string, mixed> $defaults
     * @return list<array<string, mixed>>
     */
    private function columns(string $id, array $defaults): array {
        $schema = $this->resources->getStyleBundle($id);
        $columns = [];

        foreach ($defaults as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $meta = array_get_value($schema, $key);
            $meta = is_array($meta) ? $meta : [];
            $type = $this->type($meta);
            $rule = array_get_value($meta, 'rule');
            $presentation = array_get_value($meta, 'presentation');

            $columns[] = [
                'name' => strval($key),
                'title' => $this->title("{$id}.{$key}", strval($key)),
                'type' => $type->value,
                'presentation' => is_string($presentation) ? $presentation : 'plain',
                'readonly' => array_get_value($meta, 'readonly') === true,
                'rule' => is_array($rule) ? array_map(strval(...), array_values($rule)) : [$type->rule()],
                'default' => $value,
                'placeholder' => strval($value)
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(string $relative): array {
        $defaults = $this->resources->getDefaults($relative);

        return $defaults === null ? [] : $defaults;
    }

    private function heading(): ?string {
        $menu = app(AdminPermission::class)->getCurrentMenu();

        return $menu === null ? null : i18n($menu->token());
    }

    private function identify(ResourceGroup $group, string $name): string {
        return "{$group->value}/{$name}";
    }

    private function locked(string $relative): ?ResourceOverride {
        return ResourceOverride::query()
            ->where('bundle', $relative)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param array<string, mixed> $defaults
     * @param list<array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private function payload(string $id, string $name, string $relative, array $defaults, array $columns): array {
        $override = $this->resources->getOverrides($relative);
        $override = $override === null ? [] : $override;
        $data = ['id' => $name];

        foreach ($columns as $column) {
            $key = strval($column['name']);

            $data[$key] = array_key_exists($key, $override) ? $override[$key] : $column['default'];
        }

        return [
            'title' => $this->heading(),
            'subtitle' => $this->title($id, $name),
            'breadcrumbs' => $this->breadcrumbs(),
            'id' => $id,
            'columns' => $columns,
            'data' => $data,
            'default' => $defaults,
            'actions' => [$this->action('update', $this->pinned ? 'update' : '{id}/update')]
        ];
    }

    private function relative(ResourceGroup $group, string $name): string {
        return "{$group->directory()}/{$name}";
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ResourceGroup $group, string $name): array {
        return [
            'id' => $name,
            'name' => $this->title($this->identify($group, $name), $name),
            'actions' => ['edit']
        ];
    }

    /**
     * @param array<string, mixed> $override
     */
    private function save(string $relative, ?ResourceOverride $record, array $override): void {
        if ($override === []) {
            $record?->delete();
        } else {
            $target = $record === null ? new ResourceOverride() : $record;

            $target->bundle = $relative;
            $target->data = $override;

            $target->save();
        }

        $this->resources->forget();
    }

    private function title(string $token, string $fallback): string {
        $label = i18n("resource.{$token}");

        return $label === "resource.{$token}" ? $fallback : $label;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function type(array $meta): ColumnType {
        $declared = array_get_value($meta, 'type');
        $type = is_string($declared) ? ColumnType::tryFrom($declared) : null;

        return $type === null ? ColumnType::Text : $type;
    }

    /**
     * @param array<string, array<string, mixed>> $writable
     * @param array<string, mixed> $values
     */
    private function validate(array $writable, array $values): void {
        $rules = [];

        foreach ($writable as $key => $column) {
            $rule = array_get_value($column, 'rule');
            $rules[str_replace('.', '\\.', $key)] = is_array($rule) ? $rule : [];
        }

        Validator::make(array_intersect_key($values, $writable), $rules)->validate();
    }

    private function verify(ResourceGroup $group, string $name): void {
        if (!in_array($name, $this->resources->bundleNames($group->directory()), true)) {
            error('data-not-found', 404);
        }
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param array<string, mixed> $values
     * @return array<string, array<string, mixed>>
     */
    private function writable(array $columns, array $values): array {
        $writable = [];

        foreach ($columns as $column) {
            $key = strval(array_get_value($column, 'name'));

            if (array_get_value($column, 'readonly') !== true && array_key_exists($key, $values)) {
                $writable[$key] = $column;
            }
        }

        return $writable;
    }

}
