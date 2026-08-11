<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
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

        foreach (array_keys($this->menus->bundle()) as $path) {
            $menu = $this->menus->node($path);

            if ($menu === null || $menu->ranking === null || ($menu->group && $this->denied($menu->path, $menu->tag))) {
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

    /**
     * @return array<string, mixed>
     */
    private function collect(): array {
        $group = $this->user->group_id === null ? [] : $this->load("permission/Group/{$this->user->group_id}");
        $own = $this->load("permission/User/{$this->user->id}");

        return array_replace_recursive($group, $own);
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

    /**
     * @return array<string, mixed>
     */
    private function load(string $path): array {
        $data = Storage::json($path);

        return is_array($data) ? $data : [];
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
