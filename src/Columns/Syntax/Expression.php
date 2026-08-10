<?php //>

namespace MatrixPlatform\Columns\Syntax;

class Expression {

    /**
     * @param array<string, list<Condition>> $conditions
     * @param list<string> $path
     */
    public function __construct(
        public readonly ?string $aggregate,
        public readonly array $conditions,
        public readonly ?string $field,
        public readonly ?string $function,
        public readonly array $path
    ) {}

    public function alias(): string {
        return implode('__', $this->path);
    }

}
