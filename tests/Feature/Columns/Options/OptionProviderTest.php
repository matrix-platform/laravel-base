<?php //>

namespace Tests\Feature\Columns\Options;

use Illuminate\Support\Carbon;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\RelationOptions;
use MatrixPlatform\Columns\Options\StaticOptions;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\Relic;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;

class OptionProviderTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useMenuFixtures('authority');
    }

    private function gadget(string $title, ?Carbon $enable, ?Carbon $disable = null): Gadget {
        return Gadget::forceCreate(['title' => $title, 'enable_time' => $enable, 'disable_time' => $disable]);
    }

    private function relic(string $label, ?int $parent = null): Relic {
        return Relic::forceCreate(['label' => $label, 'relic_id' => $parent]);
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

    public function test_a_soft_deleted_relation_is_left_out_by_default(): void {
        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label')));

        $this->relic('kept');

        $gone = $this->relic('gone');

        $gone->delete();

        $options = (new RelationOptions(Relic::class))->options();

        $this->assertCount(1, $options);
        $this->assertSame('kept', $options[0]->title);
        $this->assertFalse($options[0]->deleted);
    }

    public function test_asking_for_trashed_includes_soft_deleted_relations_and_flags_them(): void {
        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label')));

        $this->relic('kept');

        $gone = $this->relic('gone');

        $gone->delete();

        $options = (new RelationOptions(Relic::class))->options(null, true);

        $this->assertCount(2, $options);
        $this->assertSame([false, true], [$options[0]->deleted, $options[1]->deleted]);
        $this->assertSame('gone', $options[1]->title);
    }

    public function test_a_trashed_tree_keeps_the_children_of_a_soft_deleted_parent(): void {
        app(MetadataRegistry::class)->register(Relic::class, new StubDeclaration(new Metadata('relic', 'label', 'relic')));

        $root = $this->relic('root');

        $this->relic('child', $root->id);

        $root->delete();

        $options = (new RelationOptions(Relic::class))->options(null, true);

        $this->assertCount(1, $options);
        $this->assertTrue($options[0]->deleted);
        $this->assertSame('child', $options[0]->children[0]->title);
        $this->assertFalse($options[0]->children[0]->deleted);
    }

    public function test_a_model_without_soft_deletes_ignores_the_trashed_flag(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));

        $this->trinket('alpha');

        $options = (new RelationOptions(Trinket::class))->options(null, true);

        $this->assertCount(1, $options);
        $this->assertFalse($options[0]->deleted);
    }

    public function test_a_missing_ranking_falls_back_to_zero(): void {
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label')));

        $this->trinket('alpha');

        $this->assertSame(0, (new RelationOptions(Trinket::class))->options()[0]->ranking);
    }


    public function test_rows_outside_their_schedule_are_hidden_by_default(): void {
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget', 'title', enable: 'enable_time', disable: 'disable_time')));

        $this->gadget('published', now()->subDay());
        $this->gadget('not yet', now()->addDay());
        $this->gadget('expired', now()->subWeek(), now()->subDay());
        $this->gadget('draft', null);

        $options = (new RelationOptions(Gadget::class))->options();

        $this->assertSame(['published'], array_map(fn (Option $option): string => $option->title, $options));
    }

    public function test_turning_the_active_flag_off_offers_every_row(): void {
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget', 'title', enable: 'enable_time', disable: 'disable_time')));

        $this->gadget('published', now()->subDay());
        $this->gadget('draft', null);

        $this->assertCount(2, (new RelationOptions(Gadget::class, false))->options());
    }

    public function test_a_model_without_a_declared_schedule_is_never_filtered(): void {
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget', 'title')));

        $this->gadget('published', now()->subDay());
        $this->gadget('draft', null);

        $this->assertCount(2, (new RelationOptions(Gadget::class))->options());
    }

    public function test_asking_for_trashed_overrides_the_active_flag(): void {
        app(MetadataRegistry::class)->register(Gadget::class, new StubDeclaration(new Metadata('gadget', 'title', enable: 'enable_time', disable: 'disable_time')));

        $this->gadget('published', now()->subDay());
        $this->gadget('draft', null);

        $this->assertCount(2, (new RelationOptions(Gadget::class))->options(null, true));
    }

}
