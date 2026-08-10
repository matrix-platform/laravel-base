<?php //>

namespace MatrixPlatform\Columns\Syntax;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\OptionProvider;
use MatrixPlatform\Columns\Presentation;

class ParsedColumn {

    /**
     * @param string|list<string>|null $op
     * @param list<string> $rule
     */
    public function __construct(
        public readonly Expression $expression,
        public readonly ?string $group,
        public readonly string $name,
        public readonly string|array|null $op,
        public readonly bool $opGiven,
        public readonly ?OptionProvider $options,
        public readonly ?string $optionsName,
        public readonly ?string $path,
        public readonly ?string $placeholder,
        public readonly Presentation|string|null $presentation,
        public readonly bool $readonly,
        public readonly ?string $remark,
        public readonly bool $required,
        public readonly array $rule,
        public readonly ?bool $sortable,
        public readonly ?string $title,
        public readonly ?ColumnType $type,
        public readonly bool $virtual
    ) {}

}
