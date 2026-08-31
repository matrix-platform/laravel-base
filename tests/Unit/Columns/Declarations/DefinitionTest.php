<?php //>

namespace Tests\Unit\Columns\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Columns\Presentation;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DefinitionTest extends TestCase {

    public function test_the_constructor_is_private(): void {
        $this->assertTrue((new ReflectionMethod(Definition::class, '__construct'))->isPrivate());
    }

    public function test_every_named_constructor_carries_its_own_column_type(): void {
        $this->assertSame(ColumnType::Boolean, Definition::boolean()->type);
        $this->assertSame(ColumnType::Date, Definition::date()->type);
        $this->assertSame(ColumnType::DateTime, Definition::dateTime()->type);
        $this->assertSame(ColumnType::Float, Definition::float()->type);
        $this->assertSame(ColumnType::Integer, Definition::integer()->type);
        $this->assertSame(ColumnType::Json, Definition::json()->type);
        $this->assertSame(ColumnType::Text, Definition::text()->type);
    }

    public function test_translatable_defaults_to_false(): void {
        $this->assertFalse(Definition::text()->translatable);
    }

    public function test_translatable_can_be_turned_on(): void {
        $this->assertTrue(Definition::text(translatable: true)->translatable);
    }

    public function test_presentation_rule_and_options_are_carried_through(): void {
        $options = new StaticOptions([]);
        $definition = Definition::integer(Presentation::Select, ['required'], $options);

        $this->assertSame(Presentation::Select, $definition->presentation);
        $this->assertSame(['required'], $definition->rule);
        $this->assertSame($options, $definition->options);
    }

}
