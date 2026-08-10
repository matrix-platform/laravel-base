<?php //>

namespace MatrixPlatform\Support;

class Metadata {

    public function __construct(
        public readonly string $alias,
        public readonly string $title = 'title',
        public readonly ?string $parent = null
    ) {}

}
