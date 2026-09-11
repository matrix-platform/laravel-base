<?php //>

namespace MatrixPlatform\Support\Scaffold;

use MatrixPlatform\Columns\ColumnType;

class ResolvedColumn {

    /**
     * @param list<string> $missingLocales
     */
    public function __construct(
        public readonly string $name,
        public readonly ColumnType $type,
        public readonly bool $nullable,
        public readonly bool $translatable,
        public readonly bool $unique,
        public readonly ?string $foreignTable,
        public readonly ?string $comment,
        public readonly array $missingLocales = []
    ) {}

}
