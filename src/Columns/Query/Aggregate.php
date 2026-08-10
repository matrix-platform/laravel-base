<?php //>

namespace MatrixPlatform\Columns\Query;

use MatrixPlatform\Columns\Syntax\Condition;

class Aggregate {

    /**
     * @param array<string, list<Condition>> $conditions
     */
    public function __construct(
        public readonly string $aggregate,
        public readonly string $name,
        public readonly ?string $field,
        public readonly array $conditions
    ) {}

}
