<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Resources;

class AdminPermission {

    private $current;
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

        if (defined('USER_ID') && USER_ID !== 1) {
            Arr::forget($this->menus, tokenize(config('matrix.exclude-admin-menu-nodes')));
        }

        $u = json_decode(Storage::get("permission/User/{$user->id}", '{}'), true);
        $g = json_decode(Storage::get("permission/Group/{$user->group_id}", '{}'), true);

        $this->permissions = $u ? ($g ? array_replace_recursive($g, $u) : $u) : $g;
    }

    public function getCurrentMenu() {
        if ($this->current === null) {
            $prefix = config('matrix.admin-api-prefix');
            $path = preg_replace("/^{$prefix}\/(.+)$/u", '$1', Request::route()->uri());
            $menu = Arr::get($this->menus, $path);

            if (!$menu || $this->denied(empty($menu['group']) ? $menu['parent'] : $path, $menu['tag'])) {
                $this->current = false;
            } else {
                $this->current = $menu;
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

    private function denied($path, $tag) {
        return empty($permissions[$path][$tag]) && $tag !== 'user' && USER_LEVEL > ($tag === 'system' ? 1 : 2);
    }

}
