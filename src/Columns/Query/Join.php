<?php //>

namespace MatrixPlatform\Columns\Query;

class Join {

    /**
     * @param list<Aggregate> $aggregates
     */
    public function __construct(
        public readonly string $alias,
        public readonly string $table,
        public readonly string $target,
        public readonly string $key,
        public readonly string $foreign,
        public readonly bool $referenced,
        public readonly array $aggregates
    ) {}

}
