<?php //>

namespace MatrixPlatform\Support;

class Menus {

    /**
     * @var array<string, mixed>|null
     */
    private ?array $combined = null;

    /**
     * @var array<string, string>
     */
    private array $origins = [];

    /**
     * @return array<string, mixed>
     */
    public function bundle(): array {
        if ($this->combined === null) {
            $this->combined = $this->combine();
        }

        return $this->combined;
    }

    public function has(string $path): bool {
        return array_key_exists($path, $this->bundle());
    }

    public function node(string $path): ?MenuNode {
        $node = array_get_value($this->bundle(), $path);

        return is_array($node) ? $this->build($path, $node) : null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function build(string $path, array $node): MenuNode {
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

}
