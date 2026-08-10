<?php //>

namespace Tests\Unit\Columns;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\ManipulationType;
use PHPUnit\Framework\TestCase;

class ColumnTypeTest extends TestCase {

    public function test_every_case_has_a_validation_rule(): void {
        foreach (ColumnType::cases() as $type) {
            $this->assertNotSame('', $type->rule(), $type->value);
        }
    }

    public function test_the_rules_match_the_original_mapping(): void {
        $this->assertSame('boolean', ColumnType::Boolean->rule());
        $this->assertSame('date', ColumnType::Date->rule());
        $this->assertSame('date', ColumnType::DateTime->rule());
        $this->assertSame('numeric', ColumnType::Float->rule());
        $this->assertSame('integer', ColumnType::Integer->rule());
        $this->assertSame('array', ColumnType::Json->rule());
        $this->assertSame('string', ColumnType::Text->rule());
    }

    public function test_the_laravel_cast_aliases_are_covered(): void {
        $expected = [
            'bool' => ColumnType::Boolean,
            'boolean' => ColumnType::Boolean,
            'date' => ColumnType::Date,
            'immutable_date' => ColumnType::Date,
            'datetime' => ColumnType::DateTime,
            'datetime:Y-m-d' => ColumnType::DateTime,
            'timestamp' => ColumnType::DateTime,
            'decimal:2' => ColumnType::Float,
            'double' => ColumnType::Float,
            'float' => ColumnType::Float,
            'real' => ColumnType::Float,
            'int' => ColumnType::Integer,
            'integer' => ColumnType::Integer,
            'array' => ColumnType::Json,
            'collection' => ColumnType::Json,
            'json' => ColumnType::Json,
            'object' => ColumnType::Json,
            'hashed' => ColumnType::Text,
            'string' => ColumnType::Text
        ];

        foreach ($expected as $cast => $type) {
            $this->assertSame($type, ColumnType::fromCast($cast), $cast);
        }
    }

    public function test_an_unknown_cast_has_no_type(): void {
        $this->assertNull(ColumnType::fromCast('nonsense'));
    }

    public function test_a_backed_enum_cast_follows_its_backing_type(): void {
        $this->assertSame(ColumnType::Text, ColumnType::fromCast(IdentityType::class));
        $this->assertSame(ColumnType::Integer, ColumnType::fromCast(ManipulationType::class));
    }

    public function test_the_two_axes_share_no_case_name_or_value(): void {
        $types = array_map(fn (ColumnType $case): string => $case->name, ColumnType::cases());
        $presentations = array_map(fn (Presentation $case): string => $case->name, Presentation::cases());

        $this->assertSame([], array_intersect($types, $presentations));

        $types = array_map(fn (ColumnType $case): string => $case->value, ColumnType::cases());
        $presentations = array_map(fn (Presentation $case): string => $case->value, Presentation::cases());

        $this->assertSame([], array_intersect($types, $presentations));
    }

}
