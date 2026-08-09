<?php //>

namespace MatrixPlatform\Support;

class MenuNode {

    public function __construct(
        public readonly string $bundle,
        public readonly bool $group,
        public readonly ?string $icon,
        public readonly ?string $parent,
        public readonly string $path,
        public readonly ?int $ranking,
        public readonly ?string $tag
    ) {}

    public function token(): string {
        return "menu/{$this->bundle}.{$this->path}";
    }

}
