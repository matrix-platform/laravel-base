<?php //>

namespace MatrixPlatform\Support;

use Closure;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Declarations\Leaf;
use MatrixPlatform\Columns\Declarations\Locked;
use MatrixPlatform\Columns\Declarations\Synced;
use MatrixPlatform\Models\Menu;

class MenuLocks {

    public static function creating(Menu $menu): void {
        if (self::$released > 0) {
            return;
        }

        $parent = $menu->parent_id === null ? null : Menu::query()->find($menu->parent_id);

        if ($parent instanceof Menu && self::leaf($parent)) {
            error('menu-depth-exceeded');
        }

        if (self::synced($menu)) {
            error('menu-locked');
        }
    }

    public static function deleting(Menu $menu): void {
        if (self::$released === 0 && self::locked($menu)) {
            error('menu-locked');
        }
    }

    public static function leaf(Menu $menu): bool {
        return app(Variants::class)->variant('menu-data', $menu, null) instanceof Leaf;
    }

    public static function locked(Menu $menu): bool {
        return app(Variants::class)->variant('menu-data', $menu, null) instanceof Locked;
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function release(Closure $callback): mixed {
        self::$released++;

        try {
            return $callback();
        } finally {
            self::$released--;
        }
    }

    private static int $released = 0;

    public static function synced(Menu $menu): bool {
        return app(Variants::class)->variant('menu-data', $menu, null) instanceof Synced;
    }

    public static function updating(Menu $menu): void {
        if (self::$released > 0) {
            return;
        }

        $original = (new Menu())->setRawAttributes($menu->getRawOriginal(), true);
        $changed = array_diff(array_keys($menu->getDirty()), ['ranking', ...array_keys(Definitions::auditings())]);

        if ($changed !== [] && (self::synced($original) || self::synced($menu))) {
            error('menu-locked');
        }
    }

}
