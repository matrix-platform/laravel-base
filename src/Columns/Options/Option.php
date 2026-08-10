<?php //>

namespace MatrixPlatform\Columns\Options;

class Option {

    /**
     * @param list<Option> $children
     */
    public function __construct(
        public readonly array $children,
        public readonly int|string $id,
        public readonly int $ranking,
        public readonly string $title
    ) {}

}
