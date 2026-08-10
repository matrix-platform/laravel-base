<?php //>

namespace MatrixPlatform\Columns\Syntax;

class Condition {

    /**
     * @param string|list<string>|null $value
     */
    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly string|array|null $value
    ) {}

}
