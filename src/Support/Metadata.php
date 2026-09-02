<?php //>

namespace MatrixPlatform\Support;

class Metadata {

    public function __construct(
        public readonly string $alias,
        public readonly string $title = 'title',
        public readonly ?string $parent = null,
        public readonly ?string $enable = null,
        public readonly ?string $disable = null,
        public readonly ?string $ranking = null
    ) {}

}
