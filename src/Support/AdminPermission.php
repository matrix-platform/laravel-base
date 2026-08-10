<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\User;

class AdminPermission {

    /**
     * @var array<string, mixed>
     */
    private array $bundle;

    private ?MenuNode $current = null;

    private AdminLevel $level;

    /**
     * @var array<string, string>
     */
    private array $origins = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $permissions = null;

    public function __construct(private User $user) {
        $this->bundle = $this->combine();
        $this->level = AdminLevel::of($user->id);
    }

    public function getCurrentMenu(): ?MenuNode {
        if ($this->current === null) {
            $this->current = $this->resolve();
        }

        return $this->current;
    }

    /**
     * @return array<string, array{icon: ?string, ranking: ?int, parent: ?string, group: bool, tag: ?string, title: string}>
     */
    public function getMenuNodes(): array {
        $nodes = [];

        foreach ($this->bundle as $path => $node) {
            if (!is_array($node) || array_get_value($node, 'ranking') === null) {
                continue;
            }

            $menu = $this->node($path, $node);

            if ($menu->group && $this->denied($path, $menu->tag)) {
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

    /**
     * @return array<string, mixed>
     */
    private function combine(): array {
        $configured = config('matrix.admin-menus');
        $menus = [];

        foreach (tokenize(is_string($configured) ? $configured : null) as $name) {
            $bundle = app(Resources::class)->getMenuBundle($name);

            if ($bundle === null) {
                continue;
            }

            $this->origins += array_fill_keys(array_keys($bundle), $name);

            $menus = array_replace_recursive($bundle, $menus);
        }

        return $menus;
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

    /**
     * @param array<string, mixed> $node
     */
    private function node(string $path, array $node): MenuNode {
        $icon = array_get_value($node, 'icon');
        $parent = array_get_value($node, 'parent');
        $ranking = array_get_value($node, 'ranking');
        $tag = array_get_value($node, 'tag');

        return new MenuNode(
            array_key_exists($path, $this->origins) ? $this->origins[$path] : '',
            array_get_value($node, 'group') === true,
            is_string($icon) ? $icon : null,
            is_string($parent) ? $parent : null,
            $path,
            is_int($ranking) ? $ranking : null,
            is_string($tag) ? $tag : null
        );
    }

    private function resolve(): ?MenuNode {
        $configured = config('matrix.admin-api-prefix');
        $route = request()->route();
        $prefix = (is_string($configured) ? $configured : '') . '/';
        $uri = $route instanceof Route ? $route->uri() : '';

        if (!str_starts_with($uri, $prefix)) {
            return null;
        }

        $path = substr($uri, strlen($prefix));
        $node = array_get_value($this->bundle, $path);

        if (!is_array($node)) {
            return null;
        }

        $menu = $this->node($path, $node);

        return $this->denied($menu->group ? $path : $menu->parent, $menu->tag) ? null : $menu;
    }

}
