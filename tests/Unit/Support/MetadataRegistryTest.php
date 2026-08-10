<?php //>

namespace Tests\Unit\Support;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;

class MetadataRegistryTest extends TestCase {

    public function test_an_undeclared_model_has_no_metadata(): void {
        $this->assertNull((new MetadataRegistry())->of(Widget::class));
    }

    public function test_an_undeclared_model_has_no_definitions(): void {
        $this->assertNull((new MetadataRegistry())->definitions(Widget::class));
    }

    public function test_an_unknown_class_has_no_metadata(): void {
        $this->assertNull((new MetadataRegistry())->of('App\\Models\\Nonexistent'));
    }

    public function test_an_attribute_on_the_model_is_resolved_without_registration(): void {
        $registry = new MetadataRegistry();
        $definitions = $registry->definitions(User::class);

        $this->assertSame('username', $registry->of(User::class)?->title);
        $this->assertNotNull($definitions);
        $this->assertSame(ColumnType::Boolean, $definitions['disabled']->type);
    }

    public function test_a_registration_overrides_the_attribute(): void {
        $registry = new MetadataRegistry();

        $registry->register(User::class, new StubDeclaration(new Metadata('user', 'label')));

        $this->assertSame('label', $registry->of(User::class)?->title);
    }

    public function test_a_registered_declaration_carries_its_metadata(): void {
        $registry = new MetadataRegistry();

        $registry->register(Widget::class, new StubDeclaration(new Metadata('gizmos', 'label', 'parent')));

        $metadata = $registry->of(Widget::class);

        $this->assertNotNull($metadata);
        $this->assertSame('gizmos', $metadata->alias);
        $this->assertSame('parent', $metadata->parent);
        $this->assertSame('label', $metadata->title);
    }

    public function test_a_registered_declaration_carries_its_definitions(): void {
        $registry = new MetadataRegistry();

        $registry->register(Widget::class, new StubDeclaration(new Metadata('widget'), [
            'ranking' => new Definition(ColumnType::Integer)
        ]));

        $definitions = $registry->definitions(Widget::class);

        $this->assertNotNull($definitions);
        $this->assertSame(ColumnType::Integer, $definitions['ranking']->type);
    }

}
