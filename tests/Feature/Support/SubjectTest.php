<?php //>

namespace Tests\Feature\Support;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\Subject;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class SubjectTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->declare(Widget::class, new Metadata('widget'));
        $this->declare(Trinket::class, new Metadata('trinket', 'label', 'widget'));
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    private function declare(string $model, Metadata $metadata): void {
        app(MetadataRegistry::class)->register($model, new StubDeclaration($metadata));
    }

    private function subject(): Subject {
        return app(Subject::class);
    }

    public function test_a_model_without_a_parent_is_its_own_prefix(): void {
        $this->assertSame('widget', $this->subject()->prefix(new Widget()));
    }

    public function test_a_child_prefix_carries_the_foreign_key_placeholder(): void {
        $this->assertSame('widget/{widget_id}/trinket', $this->subject()->prefix(new Trinket()));
    }

    public function test_the_foreign_key_of_the_parent_relation_is_exposed(): void {
        $this->assertSame('widget_id', $this->subject()->foreign(new Trinket()));
        $this->assertNull($this->subject()->foreign(new Widget()));
    }

    public function test_the_alias_comes_from_the_metadata(): void {
        $this->assertSame('trinket', $this->subject()->alias(new Trinket()));
    }

    public function test_a_self_referencing_parent_stops_the_prefix(): void {
        $this->declare(Trinket::class, new Metadata('trinket', 'label', 'trinket'));

        $this->assertSame('trinket', $this->subject()->prefix(new Trinket()));
    }

    public function test_two_models_pointing_at_each_other_do_not_recurse_forever(): void {
        $this->declare(Widget::class, new Metadata('widget', 'title', 'pinned'));
        $this->declare(Trinket::class, new Metadata('trinket', 'label', 'widget'));

        $this->assertSame('widget/{widget_id}/trinket', $this->subject()->prefix(new Trinket()));
    }

    public function test_a_parent_relation_that_is_not_a_belongs_to_is_rejected(): void {
        $this->declare(Widget::class, new Metadata('widget', 'title', 'tagged'));

        $this->expectException(ServiceException::class);

        $this->subject()->prefix(new Widget());
    }

    public function test_an_undeclared_model_is_rejected(): void {
        $this->expectException(ServiceException::class);

        $this->subject()->prefix(new Gadget());
    }

    public function test_the_title_reads_the_declared_column(): void {
        $trinket = Trinket::forceCreate(['label' => 'labelled']);

        $this->assertSame('labelled', $this->subject()->title($trinket));
    }

    public function test_the_title_collapses_a_translatable_declared_column_to_the_current_locale(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget', 'translated'), [
            'translated' => Definition::text(translatable: true)
        ]));

        $widget = Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        app()->setLocale('tw');
        $this->assertSame('Alpha', $this->subject()->title($widget));

        app()->setLocale('en');
        $this->assertSame('Beta', $this->subject()->title($widget));
    }

    public function test_the_parents_chain_is_resolved_from_the_source(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha']);
        $trinket = Trinket::forceCreate(['label' => 'a1', 'widget_id' => $widget->id]);

        $parents = $this->subject()->parents($trinket, $trinket);

        $this->assertCount(1, $parents);
        $this->assertSame($widget->id, $parents[0]->getKey());
    }

    public function test_the_parents_chain_can_be_resolved_from_an_array(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha']);

        $parents = $this->subject()->parents(new Trinket(), ['widget_id' => $widget->id]);

        $this->assertCount(1, $parents);
        $this->assertSame($widget->id, $parents[0]->getKey());
    }

    public function test_a_missing_parent_row_stops_the_chain(): void {
        $trinket = Trinket::forceCreate(['label' => 'orphan', 'widget_id' => 999999]);

        $this->assertSame([], $this->subject()->parents($trinket, $trinket));
    }

    public function test_rows_pointing_at_each_other_do_not_recurse_forever(): void {
        $this->declare(Trinket::class, new Metadata('trinket', 'label', 'trinket'));

        $first = Trinket::forceCreate(['label' => 'first']);
        $second = Trinket::forceCreate(['label' => 'second', 'trinket_id' => $first->id]);

        $first->trinket_id = $second->id;
        $first->save();

        $parents = $this->subject()->parents($first, $first);

        $this->assertSame([$second->id, $first->id], array_map(fn ($parent) => $parent->getKey(), $parents));
    }

}
