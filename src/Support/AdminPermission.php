<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\User;

class AdminPermission {

    private $current;
    private $level = User::ROOT;
    private $menus;
    private $permissions;

    public function __construct($user) {
        foreach (tokenize(config('matrix.admin-menus')) as $name) {
            $bundle = app(Resources::class)->getAdminMenuBundle($name);

            foreach ($bundle as $path => &$menu) {
                $menu['title'] = "menu/{$name}.{$path}";
            }

            $this->menus = $this->menus ? array_replace_recursive($bundle, $this->menus) : $bundle;
        }

        if ($user->id !== User::ROOT) {
            Arr::forget($this->menus, tokenize(config('matrix.exclude-admin-menu-nodes')));

            $this->level = $user->id > 1000 ? User::REGULAR : User::ADMIN;
        }

        $u = json_decode(rescue(fn () => Storage::get("permission/User/{$user->id}"), '{}'), true);
        $g = json_decode(rescue(fn () => Storage::get("permission/Group/{$user->group_id}"), '{}'), true);

        $this->permissions = $u ? ($g ? array_replace_recursive($g, $u) : $u) : $g;
    }

    public function getCurrentMenu() {
        if ($this->current === null) {
            $prefix = config('matrix.admin-api-prefix');
            $path = preg_replace("/^{$prefix}\/(.+)$/u", '$1', Request::route()->uri());
            $menu = data_get($this->menus, $path);

            if (!$menu || $this->denied(empty($menu['group']) ? $menu['parent'] : $path, $menu['tag'])) {
                $this->current = false;
            } else {
                $this->current = $menu;
                $this->current['path'] = $path;
            }
        }

        return $this->current;
    }

    public function getMenuNodes() {
        $nodes = [];

        foreach ($this->menus ?: [] as $path => $menu) {
            if (empty($menu['ranking'])) {
                continue;
            }

            if (!empty($menu['group']) && $this->denied($path, $menu['tag'])) {
                continue;
            }

            $menu['title'] = i18n($menu['title']);

            $nodes[$path] = $menu;
        }

        return $nodes;
    }

    public function getMenus() {
        return $this->menus;
    }

    private function denied($path, $tag) {
        return empty($this->permissions[$path][$tag]) && $tag !== 'user' && $this->level > ($tag === 'system' ? User::ROOT : User::ADMIN);
    }

}
