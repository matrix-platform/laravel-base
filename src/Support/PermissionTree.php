<?php //>

namespace MatrixPlatform\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\OptionProvider;

class PermissionTree implements OptionProvider {

    public const ACTIONS = ['query', 'delete', 'insert', 'update'];

    /**
     * @var array<string, array<string, int>>|null
     */
    private ?array $grants = null;

    /**
     * @var array<string, MenuNode>|null
     */
    private ?array $nodes = null;

    /**
     * @var array<string, ?string>|null
     */
    private ?array $owners = null;

    public function __construct(private Menus $menus) {}

    public function filter(): Closure {
        return function (Model $model): void {
            $current = $model->getOriginal('permissions');
            $requested = $model->getAttribute('permissions');

            $model->setAttribute('permissions', $this->revise(is_array($current) ? $current : [], is_array($requested) ? $requested : []));
        };
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function grants(): array {
        if ($this->grants === null) {
            $this->grants = $this->granting();
        }

        return $this->grants;
    }

    /**
     * @return list<Option>
     */
    public function options(?Model $model = null): array {
        $children = $this->nesting();
        $grants = $this->grants();
        $owners = $this->owners();
        $sections = [];

        foreach ($this->nodes() as $path => $node) {
            if (!$node->group || $owners[$path] !== null) {
                continue;
            }

            $header = $this->header($node);

            $sections[$header === null ? '' : $header->path][] = $this->entry($grants, $children, $node);
        }

        $options = [];

        foreach ($sections as $key => $items) {
            $header = $key === '' ? null : $this->nodes()[$key];

            $options[] = new Option($items, $key, $this->ranking($header), $header === null ? '' : i18n($header->token()));
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $requested
     * @return array<string, array<string, bool>>
     */
    public function revise(array $current, array $requested): array {
        $permission = app(AdminPermission::class);
        $revised = $current;

        foreach ($this->grants() as $path => $actions) {
            foreach (array_keys($actions) as $tag) {
                if (!$permission->permits($path, $tag)) {
                    continue;
                }

                if ($this->wanted($requested, $path, $tag)) {
                    $revised[$path][$tag] = true;
                } else {
                    unset($revised[$path][$tag]);
                }
            }
        }

        return array_filter($revised, fn (mixed $actions): bool => is_array($actions) && $actions !== []);
    }

    /**
     * @param callable(MenuNode): bool $match
     */
    private function ascend(MenuNode $node, callable $match): ?MenuNode {
        $nodes = $this->nodes();
        $parent = $node->parent;

        while ($parent !== null && array_key_exists($parent, $nodes)) {
            $found = $nodes[$parent];

            if ($match($found)) {
                return $found;
            }

            $parent = $found->parent;
        }

        return null;
    }

    /**
     * @param array<string, array<string, int>> $grants
     * @param array<string, list<MenuNode>> $children
     */
    private function entry(array $grants, array $children, MenuNode $node): Option {
        $items = [];

        foreach ($grants[$node->path] as $action => $ranking) {
            $items[] = new Option([], $action, $ranking, i18n("permission.{$action}"));
        }

        foreach (array_key_exists($node->path, $children) ? $children[$node->path] : [] as $child) {
            $items[] = $this->entry($grants, $children, $child);
        }

        return new Option($items, $node->path, $this->ranking($node), i18n($node->token()));
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function granting(): array {
        $owners = $this->owners();
        $tags = [];

        foreach ($this->nodes() as $path => $node) {
            if ($node->group && !array_key_exists($path, $tags)) {
                $tags[$path] = [];
            }

            $owner = $node->group ? $path : $owners[$path];

            if ($node->tag !== null && $owner !== null) {
                $tags[$owner][$node->tag] = true;
            }
        }

        $grants = [];

        foreach ($tags as $path => $granted) {
            $grants[$path] = [];

            foreach (self::ACTIONS as $ranking => $action) {
                if (array_key_exists($action, $granted)) {
                    $grants[$path][$action] = $ranking;
                }
            }
        }

        return $grants;
    }

    private function header(MenuNode $node): ?MenuNode {
        return $this->ascend($node, fn (MenuNode $found): bool => !$found->group && $found->tag === null);
    }

    /**
     * @return array<string, list<MenuNode>>
     */
    private function nesting(): array {
        $owners = $this->owners();
        $children = [];

        foreach ($this->nodes() as $path => $node) {
            if ($node->group && $owners[$path] !== null) {
                $children[strval($owners[$path])][] = $node;
            }
        }

        return $children;
    }

    /**
     * @return array<string, MenuNode>
     */
    private function nodes(): array {
        if ($this->nodes === null) {
            $this->nodes = $this->menus->nodes();
        }

        return $this->nodes;
    }

    /**
     * @return array<string, ?string>
     */
    private function owners(): array {
        if ($this->owners !== null) {
            return $this->owners;
        }

        $owners = [];

        foreach ($this->nodes() as $path => $node) {
            $found = $this->ascend($node, fn (MenuNode $found): bool => $found->group);

            $owners[$path] = $found === null ? null : $found->path;
        }

        $this->owners = $owners;

        return $owners;
    }

    private function ranking(?MenuNode $node): int {
        return $node === null || $node->ranking === null ? 0 : $node->ranking;
    }

    /**
     * @param array<string, mixed> $requested
     */
    private function wanted(array $requested, string $path, string $tag): bool {
        $actions = array_get_value($requested, $path);

        return is_array($actions) && array_get_value($actions, $tag) == true;
    }

}
