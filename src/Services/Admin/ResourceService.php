<?php //>

namespace MatrixPlatform\Services\Admin;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Support\Actions;
use MatrixPlatform\Support\AdminPermission;
use MatrixPlatform\Support\CfgSchema;
use MatrixPlatform\Support\Menus;
use MatrixPlatform\Support\ResourceGroup;
use MatrixPlatform\Support\Resources;

/**
 * @phpstan-import-type Column from CfgSchema
 */
class ResourceService {

    private bool $pinned = false;

    private string $prefix = 'resource';

    public function __construct(private Resources $resources, private Actions $actions, private CfgSchema $schema) {}

    /**
     * @return array<string, mixed>
     */
    public function get(ResourceGroup $group, string $name): array {
        $this->verify($group, $name);

        $id = $this->identify($group, $name);
        $relative = $this->relative($group, $name);
        $defaults = $this->defaults($relative);

        return $this->payload($id, $name, $relative, $defaults, $this->schema->columns($group, $id, $defaults));
    }

    /**
     * @return array<string, mixed>
     */
    public function list(ResourceGroup $group, bool $unrestricted): array {
        $existing = $this->resources->bundleNames($group->directory());
        $names = $unrestricted ? $existing : array_values(array_intersect($this->allowed($group), $existing));
        $rows = array_map(fn (string $name): array => $this->row($group, $name), $names);

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
        $columns = $this->schema->columns($group, $id, $defaults);
        $record = $this->locked($relative);
        $override = $this->schema->apply($record === null ? [] : $record->data, $columns, is_array($input) ? $input : [], $defaults);

        $this->save($relative, $record, $override);

        return $this->payload($id, $name, $relative, $defaults, $columns, $override);
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

            $menu = $menus->above($menu);
        }

        return array_reverse($crumbs);
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
     * @param list<Column> $columns
     * @param array<string, mixed>|null $override the values just written, so a write inside a transaction never reads them back through the cache
     * @return array<string, mixed>
     */
    private function payload(string $id, string $name, string $relative, array $defaults, array $columns, ?array $override = null): array {
        $override = $override === null ? $this->resources->getOverrides($relative) : $override;
        $override = $override === null ? [] : $override;
        $data = ['id' => $name];

        foreach ($columns as $column) {
            $key = $column['name'];
            $value = array_key_exists($key, $override) ? $override[$key] : $column['default'];

            $data[$key] = $column['secret'] && !$this->schema->cleared($value) ? CfgSchema::MASK : $value;
        }

        return [
            'title' => $this->heading(),
            'subtitle' => $this->schema->title($id, $name),
            'breadcrumbs' => $this->breadcrumbs(),
            'id' => $id,
            'columns' => $columns,
            'data' => $data,
            'default' => $this->redacted($defaults, $columns),
            'actions' => [$this->action('update', $this->pinned ? 'update' : '{id}/update')]
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param list<Column> $columns
     * @return array<string, mixed>
     */
    private function redacted(array $defaults, array $columns): array {
        foreach ($columns as $column) {
            if ($column['secret']) {
                $defaults[$column['name']] = '';
            }
        }

        return $defaults;
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
            'name' => $this->schema->title($this->identify($group, $name), $name),
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

        DB::afterCommit(fn () => $this->resources->forget());
    }

    private function verify(ResourceGroup $group, string $name): void {
        if (!in_array($name, $this->resources->bundleNames($group->directory()), true)) {
            error('data-not-found', 404);
        }
    }

}
