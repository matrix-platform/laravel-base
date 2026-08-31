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
    public static function boolean(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Boolean, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function date(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Date, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function dateTime(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::DateTime, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function float(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Float, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function integer(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Integer, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function json(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Json, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    public static function text(Presentation|string|null $presentation = null, array|Closure $rule = [], OptionProvider|string|null $options = null, bool $translatable = false, bool $required = false, bool $unique = false): self {
        return new self(ColumnType::Text, $presentation, $rule, $options, $translatable, $required, $unique);
    }

    /**
     * @param list<string>|Closure(): list<string> $rule
     * @param OptionProvider|class-string<OptionProvider>|null $options
     */
    private function __construct(
        public readonly ColumnType $type,
        public readonly Presentation|string|null $presentation,
        public readonly array|Closure $rule,
        public readonly OptionProvider|string|null $options,
        public readonly bool $translatable,
        public readonly bool $required,
        public readonly bool $unique
    ) {}

}
