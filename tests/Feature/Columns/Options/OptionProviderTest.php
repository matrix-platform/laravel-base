<?php //>

namespace Tests\Feature\Columns\Options;

use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\RelationOptions;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;

class OptionProviderTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('authority');
    }

    private function trinket(string $label, ?int $parent = null, int $ranking = 0): Trinket {
        return Trinket::forceCreate(['label' => $label, 'trinket_id' => $parent, 'ranking' => $ranking]);
    }

    public function test_a_bundle_becomes_a_flat_option_list(): void {
        $options = (new BundleOptions('status'))->options();

        $this->assertCount(2, $options);
        $this->assertSame('draft', $options[0]->id);
        $this->assertSame('Draft', $options[0]->title);
        $this->assertSame(0, $options[0]->ranking);
        $this->assertSame(1, $options[1]->ranking);
        $this->assertSame([], $options[0]->children);
    }

    public function test_a_missing_bundle_yields_no_options(): void {
        $this->assertSame([], (new BundleOptions('nonsense'))->options());
    }

    public function test_static_options_pass_through(): void {
        $given = [new Option([], 7, 3, 'Seven')];

        $this->assertSame($given, (new StaticOptions($given))->options());
    }

    public function test_a_relation_without_a_parent_is_a_flat_list(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));

        $this->trinket('alpha', null, 5);
        $this->trinket('beta', null, 9);

        $options = (new RelationOptions(Trinket::class))->options();

        $this->assertCount(2, $options);
        $this->assertSame('alpha', $options[0]->title);
        $this->assertSame(5, $options[0]->ranking);
    }

    public function test_a_self_referencing_relation_becomes_a_tree(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'trinket')));

        $root = $this->trinket('root');
        $child = $this->trinket('child', $root->id);

        $this->trinket('grandchild', $child->id);

        $options = (new RelationOptions(Trinket::class))->options();

        $this->assertCount(1, $options);
        $this->assertSame('root', $options[0]->title);
        $this->assertSame('child', $options[0]->children[0]->title);
        $this->assertSame('grandchild', $options[0]->children[0]->children[0]->title);
    }

    public function test_an_undeclared_ancestor_is_refused(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));

        $this->trinket('alpha');

        $this->expectExceptionMessage('undeclared-model');

        (new RelationOptions(Trinket::class))->options();
    }

    public function test_a_missing_ranking_falls_back_to_zero(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));

        $this->trinket('alpha');

        $this->assertSame(0, (new RelationOptions(Trinket::class))->options()[0]->ranking);
    }

}
