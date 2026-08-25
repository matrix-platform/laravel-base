<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Services\Admin\Crud\CopyService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class CopyServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        actor()->setUser(UserFactory::new()->createOne(['id' => User::ROOT]));

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
    }

    private function copied(Widget $source): Widget {
        return Widget::query()->findOrFail(intval($this->copier()->copy($source->id)['id']));
    }

    private function copier(): CopyService {
        return (new CopyService(Widget::class))->standalone(true);
    }

    /**
     * @param Builder<Trinket> $query
     * @return list<string>
     */
    private function labels(Builder $query): array {
        return array_values($query->orderBy('label')->pluck('label')->all());
    }

    private function widgets(): Widget {
        $widget = Widget::forceCreate(['title' => 'Alpha', 'secret' => 'hidden']);

        Widget::query()->whereKey($widget->id)->update(['ip' => '203.0.113.9', 'creator_id' => 999, 'updater_id' => 7]);

        return $widget->refresh();
    }

    public function test_a_copy_gets_a_new_key_and_keeps_the_other_columns(): void {
        $source = $this->widgets();
        $copy = $this->copied($source);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('Alpha', $copy->title);
        $this->assertSame('hidden', $copy->secret);
    }

    public function test_a_generated_column_is_regenerated_instead_of_copied(): void {
        $source = $this->widgets();
        $copy = $this->copied($source);

        $this->assertSame('203.0.113.9', $source->ip);
        $this->assertSame('127.0.0.1', $copy->ip);
    }

    public function test_a_copy_clears_the_updater_of_its_source(): void {
        $source = $this->widgets();
        $copy = $this->copied($source);

        $this->assertSame(7, $source->updater_id);
        $this->assertNull($copy->updater_id);
    }

    public function test_a_copy_belongs_to_the_current_user_not_to_the_creator_of_its_source(): void {
        $source = $this->widgets();
        $copy = $this->copied($source);

        $this->assertSame(999, $source->creator_id);
        $this->assertSame(User::ROOT, $copy->creator_id);
    }

    public function test_a_copy_gets_a_fresh_creation_timestamp_and_no_update_timestamp(): void {
        $source = $this->widgets();

        Widget::query()->whereKey($source->id)->update(['create_time' => '2000-01-01 00:00:00', 'update_time' => '2000-01-02 00:00:00']);

        $copy = $this->copied($source);

        $this->assertNotSame('2000-01-01 00:00:00', $copy->create_time->format('Y-m-d H:i:s'));
        $this->assertNull($copy->update_time);
    }

    public function test_a_copy_reuses_the_ranking_of_its_source(): void {
        $source = $this->widgets();
        $copy = $this->copied($source);

        $this->assertSame($source->ranking, $copy->ranking);
    }

    public function test_a_model_without_an_updater_column_can_still_be_copied(): void {
        $source = UserLog::forceCreate(['user_id' => User::ROOT, 'type' => UserLogType::Login]);

        $id = (new CopyService(UserLog::class))->standalone(true)->copy($source->id)['id'];

        $this->assertNotSame($source->id, $id);
        $this->assertSame(UserLogType::Login, UserLog::query()->findOrFail(intval($id))->type);
    }

    public function test_a_guard_receives_the_copy_and_its_source(): void {
        $source = $this->widgets();
        $seen = [];

        $this->copier()
            ->guard(function (Widget $copy, Widget $original) use (&$seen): void {
                $seen = ['exists' => $copy->exists, 'source' => $original->id, 'title' => $copy->title];
            })
            ->copy($source->id);

        $this->assertSame(['exists' => false, 'source' => $source->id, 'title' => 'Alpha'], $seen);
    }

    public function test_a_guard_can_refuse_a_copy(): void {
        $source = $this->widgets();

        $this->expectException(ServiceException::class);

        $this->copier()
            ->guard(fn () => error('permission-denied', 403))
            ->copy($source->id);
    }

    public function test_cascade_repoints_the_children_at_the_new_parent(): void {
        $source = $this->widgets();

        Trinket::forceCreate(['label' => 'a', 'widget_id' => $source->id]);
        Trinket::forceCreate(['label' => 'b', 'widget_id' => $source->id]);

        $id = $this->copier()->cascade(['trinkets'])->copy($source->id)['id'];

        $this->assertSame(['a', 'b'], $this->labels(Trinket::query()->where('widget_id', $id)));
        $this->assertSame(['a', 'b'], $this->labels(Trinket::query()->where('widget_id', $source->id)));
    }

    public function test_cascade_repoints_grandchildren_at_the_new_children(): void {
        $source = $this->widgets();
        $child = Trinket::forceCreate(['label' => 'child', 'widget_id' => $source->id]);

        Trinket::forceCreate(['label' => 'grandchild', 'trinket_id' => $child->id]);

        $id = $this->copier()->cascade(['trinkets.trinkets'])->copy($source->id)['id'];
        $copied = Trinket::query()->where('widget_id', $id)->sole();

        $this->assertSame('child', $copied->label);
        $this->assertNotSame($child->id, $copied->id);
        $this->assertSame(['grandchild'], $this->labels(Trinket::query()->where('trinket_id', $copied->id)));
    }

    public function test_a_morph_relation_can_be_cascaded(): void {
        $source = $this->widgets();

        Trinket::forceCreate(['label' => 'owned', 'owner_id' => $source->id, 'owner_type' => Widget::class]);

        $id = $this->copier()->cascade(['owned'])->copy($source->id)['id'];
        $copied = Trinket::query()->where('owner_id', $id)->sole();

        $this->assertSame('owned', $copied->label);
        $this->assertSame(Widget::class, $copied->owner_type);
    }

    public function test_a_belongs_to_many_relation_cannot_be_cascaded(): void {
        $source = $this->widgets();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-cascade-relation');

        $this->copier()
            ->cascade(['tagged'])
            ->copy($source->id);
    }

    public function test_a_belongs_to_relation_cannot_be_cascaded(): void {
        $source = $this->widgets();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-cascade-relation');

        $this->copier()
            ->cascade(['pinned'])
            ->copy($source->id);
    }

    public function test_a_joined_column_never_reaches_the_insert(): void {
        $widget = $this->widgets();
        $source = Trinket::forceCreate(['label' => 'mine', 'widget_id' => $widget->id]);

        $id = (new CopyService(Trinket::class))
            ->standalone(true)
            ->columns(['widget.title'])
            ->copy($source->id)['id'];

        $this->assertSame('mine', Trinket::query()->findOrFail(intval($id))->label);
    }

    public function test_a_scope_hides_the_source_from_the_copy(): void {
        $source = $this->widgets();

        $this->expectException(ModelNotFoundException::class);

        $this->copier()
            ->scope(fn (Builder $query) => $query->where('title', 'Beta'))
            ->copy($source->id);
    }

    public function test_a_copy_cannot_reach_a_row_under_another_parent(): void {
        $mine = $this->widgets();
        $other = Widget::forceCreate(['title' => 'Beta']);
        $source = Trinket::forceCreate(['label' => 'theirs', 'widget_id' => $other->id]);

        $this->expectException(ModelNotFoundException::class);

        (new CopyService(Trinket::class))->params(['widget_id' => $mine->id])->copy($source->id);
    }

    public function test_a_copy_is_recorded_in_the_audit_trail(): void {
        $source = $this->widgets();
        $before = ManipulationLog::query()->where('data_type', 'stub_widget')->count();

        $this->copied($source);

        $this->assertSame($before + 1, ManipulationLog::query()->where('data_type', 'stub_widget')->count());
    }

    public function test_cascade_copies_the_children_one_row_at_a_time(): void {
        $source = $this->widgets();
        $created = [];

        Trinket::forceCreate(['label' => 'a', 'widget_id' => $source->id]);
        Trinket::forceCreate(['label' => 'b', 'widget_id' => $source->id]);

        Trinket::created(function (Trinket $trinket) use (&$created): void {
            $created[] = $trinket->label;
        });

        $this->copier()
            ->cascade(['trinkets'])
            ->copy($source->id);

        $this->assertSame(['a', 'b'], $created);
    }

}
