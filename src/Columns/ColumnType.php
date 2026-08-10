<?php //>

namespace MatrixPlatform\Columns;

use ReflectionEnum;

enum ColumnType: string {

    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Float = 'float';
    case Integer = 'integer';
    case Json = 'json';
    case Text = 'text';

    public static function fromCast(string $cast): ?self {
        if (enum_exists($cast)) {
            $backing = (new ReflectionEnum($cast))->getBackingType();

            return $backing !== null && $backing->getName() === 'int' ? self::Integer : self::Text;
        }

        return match (explode(':', $cast, 2)[0]) {
            'bool', 'boolean' => self::Boolean,
            'date', 'immutable_date' => self::Date,
            'datetime', 'immutable_datetime', 'timestamp' => self::DateTime,
            'decimal', 'double', 'float', 'real' => self::Float,
            'int', 'integer' => self::Integer,
            'array', 'collection', 'json', 'object' => self::Json,
            'encrypted', 'hashed', 'string' => self::Text,
            default => null
        };
    }

    public function rule(): string {
        return match ($this) {
            self::Boolean => 'boolean',
            self::Date, self::DateTime => 'date',
            self::Float => 'numeric',
            self::Integer => 'integer',
            self::Json => 'array',
            self::Text => 'string'
        };
    }

}
