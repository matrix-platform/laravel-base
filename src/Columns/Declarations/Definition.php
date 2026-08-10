<?php //>

namespace MatrixPlatform\Columns\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\OptionProvider;
use MatrixPlatform\Columns\Presentation;

class Definition {

    /**
     * @param list<string> $rule
     */
    public function __construct(
        public readonly ColumnType $type = ColumnType::Text,
        public readonly Presentation|string|null $presentation = null,
        public readonly array $rule = [],
        public readonly ?OptionProvider $options = null
    ) {}

}
