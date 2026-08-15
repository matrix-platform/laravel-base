<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Routing\Route;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\User;

class AdminPermission {

    private ?MenuNode $current = null;

    private AdminLevel $level;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $permissions = null;

    private bool $resolved = false;

    public function __construct(private User $user, private Menus $menus) {
        $this->level = AdminLevel::of($user->id);
    }

    public function getCurrentMenu(): ?MenuNode {
        if (!$this->resolved) {
            $this->current = $this->resolve();
            $this->resolved = true;
        }

        return $this->current;
    }

    /**
     * @return array<string, array{icon: ?string, ranking: ?int, parent: ?string, group: bool, tag: ?string, title: string}>
     */
    public function getMenuNodes(): array {
        $nodes = [];

        foreach ($this->menus->nodes() as $path => $menu) {
            if ($menu->ranking === null || ($menu->group && $this->denied($menu->path, $menu->tag))) {
                continue;
            }

            $nodes[$path] = [
                'icon' => $menu->icon,
                'ranking' => $menu->ranking,
                'parent' => $menu->parent,
                'group' => $menu->group,
                'tag' => $menu->tag,
                'title' => i18n($menu->token())
            ];
        }

        return $nodes;
    }

    public function permits(string $path, string $tag): bool {
        return !$this->denied($path, $tag);
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array {
        $group = $this->user->group_id === null ? null : Group::query()->find($this->user->group_id);

        return array_replace_recursive($group === null ? [] : $group->permissions, $this->user->permissions);
    }

    private function denied(?string $path, ?string $tag): bool {
        if ($tag === ReservedTag::User->value) {
            return false;
        }

        $required = $tag === ReservedTag::System->value ? AdminLevel::Root : AdminLevel::Admin;

        return $this->level->value > $required->value && !$this->granted($path, $tag);
    }

    private function granted(?string $path, ?string $tag): bool {
        if ($path === null || $tag === null) {
            return false;
        }

        if ($this->permissions === null) {
            $this->permissions = $this->collect();
        }

        $node = array_get_value($this->permissions, $path);

        return is_array($node) && array_get_value($node, $tag) === true;
    }

    private function resolve(): ?MenuNode {
        $configured = config('matrix.admin-api-prefix');
        $route = request()->route();
        $prefix = (is_string($configured) ? $configured : '') . '/';
        $uri = $route instanceof Route ? $route->uri() : '';

        if (!str_starts_with($uri, $prefix)) {
            return null;
        }

        $menu = $this->menus->node(substr($uri, strlen($prefix)));

        if ($menu === null) {
            return null;
        }

        return $this->denied($menu->group ? $menu->path : $menu->parent, $menu->tag) ? null : $menu;
    }

}
