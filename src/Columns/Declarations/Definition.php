<?php //>

namespace MatrixPlatform\Columns\Declarations;

use Closure;
use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Options\OptionProvider;
use MatrixPlatform\Columns\Presentation;

class Definition {

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public function __construct(
        public readonly ColumnType $type = ColumnType::Text,
        public readonly Presentation|string|null $presentation = null,
        public readonly array|Closure $rule = [],
        public readonly OptionProvider|string|null $options = null
    ) {}

}
