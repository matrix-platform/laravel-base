<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use stdClass;
use Tests\FeatureTestCase;
use Tests\Stubs\Gizmo;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Trinket;
use Tests\Stubs\Widget;

class CrudServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $user = new User();

        $user->id = User::ROOT;

        actor()->setUser($user);

        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget')));
        app(MetadataRegistry::class)->register(Trinket::class, new StubDeclaration(new Metadata('trinket', 'label', 'widget')));
        app(MetadataRegistry::class)->register(Gizmo::class, new StubDeclaration(new Metadata('gizmo')));
    }

    /**
     * @param list<string> $labels
     */
    private function trinkets(int $widget, array $labels): void {
        foreach ($labels as $label) {
            Trinket::forceCreate(['label' => $label, 'widget_id' => $widget]);
        }
    }

    private function widgets(): Widget {
        return Widget::forceCreate(['title' => 'Alpha']);
    }

    public function test_a_scope_narrows_the_listing(): void {
        $widget = $this->widgets();

        $this->trinkets($widget->id, ['keep', 'drop']);

        $rows = (new ListService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->scope(fn (Builder $query) => $query->where('label', 'keep'))
            ->list([])['rows'];

        $this->assertSame(['keep'], array_column($rows, 'label'));
    }

    public function test_a_conditional_scope_is_skipped_when_the_condition_is_false(): void {
        $widget = $this->widgets();

        $this->trinkets($widget->id, ['keep', 'drop']);

        $rows = (new ListService(Trinket::class))
            ->standalone(true)
            ->columns(['label'])
            ->when(false, fn (Builder $query) => $query->where('label', 'keep'))
            ->list([])['rows'];

        $this->assertCount(2, $rows);
    }

    public function test_the_identifier_column_leads_the_payload_and_is_inert(): void {
        $columns = (new ListService(Widget::class))->standalone(true)->columns(['title'])->list([])['columns'];

        $this->assertSame('id', $columns[0]['name']);
        $this->assertSame('integer', $columns[0]['type']);
        $this->assertSame('hidden', $columns[0]['presentation']);
        $this->assertTrue($columns[0]['readonly']);
        $this->assertNull($columns[0]['op']);
        $this->assertFalse($columns[0]['sortable']);
    }

    public function test_the_payload_carries_the_full_column_shape(): void {
        $columns = (new ListService(Widget::class))->standalone(true)->columns(['title'])->list([])['columns'];

        $this->assertSame([
            'name', 'title', 'type', 'presentation', 'group', 'op', 'options',
            'path', 'placeholder', 'remark', 'readonly', 'required', 'rule', 'sortable'
        ], array_keys($columns[1]));
    }

    public function test_options_are_resolved_against_the_record(): void {
        $widget = $this->widgets();

        $columns = (new ListService(Trinket::class))->standalone(true)->columns(['widget_id'])->list([])['columns'];
        $options = $columns[1]['options'];

        $this->assertIsArray($options);
        $this->assertSame($widget->id, $options[0]->id);
    }

    public function test_an_accessor_is_not_reported_as_a_row_value(): void {
        Gizmo::forceCreate(['title' => 'quiet']);

        $rows = (new ListService(Gizmo::class))->standalone(true)->columns(['title'])->list([])['rows'];

        $this->assertArrayHasKey('title', $rows[0]);
        $this->assertArrayNotHasKey('shout', $rows[0]);
    }

    public function test_a_model_without_default_attributes_reports_an_empty_object(): void {
        $context = (new ListService(Trinket::class))->standalone(true)->columns(['label'])->list([])['context'];

        $this->assertInstanceOf(stdClass::class, $context);
    }

    public function test_a_hidden_attribute_never_reaches_the_editor(): void {
        $widget = Widget::forceCreate(['title' => 'Alpha', 'secret' => 'classified']);

        $data = (new GetService(Widget::class))->standalone(true)->columns(['title', 'secret'])->get($widget->id)['data'];

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayNotHasKey('secret', $data);
    }

    public function test_a_required_column_and_an_optional_one_get_different_prefixes(): void {
        try {
            (new InsertService(Widget::class))
                ->standalone(true)
                ->columns(['*title', 'secret'])
                ->insert([]);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->failed();

            $this->assertArrayHasKey('Required', $errors['title']);
            $this->assertArrayHasKey('Present', $errors['secret']);

            return;
        }

        $this->fail('the insert was expected to be rejected');
    }

    public function test_a_declared_rule_replaces_the_derived_one(): void {
        try {
            (new InsertService(Widget::class))
                ->standalone(true)
                ->columns([['name' => 'title', 'rule' => ['numeric']]])
                ->insert(['title' => 'words']);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('Numeric', $exception->validator->failed()['title']);

            return;
        }

        $this->fail('the insert was expected to be rejected');
    }

    public function test_a_readonly_column_takes_the_input_only_when_the_model_has_none(): void {
        $id = (new InsertService(User::class))
            ->standalone(true)
            ->columns(['*username', '!disabled', '!group_id'])
            ->insert(['username' => 'reader', 'disabled' => true, 'group_id' => 7])['id'];

        $user = User::query()->findOrFail(intval($id));

        $this->assertFalse($user->disabled);
        $this->assertSame(7, $user->group_id);
    }

    public function test_a_readonly_column_does_not_have_to_be_sent(): void {
        $id = (new InsertService(Trinket::class))
            ->standalone(true)
            ->columns(['*label', '!amount'])
            ->insert(['label' => 'a'])['id'];

        $this->assertNull(Trinket::query()->findOrFail(intval($id))->amount);
    }

    public function test_a_joined_column_is_neither_required_nor_written(): void {
        $widget = $this->widgets();

        $this->trinkets($widget->id, ['mine']);

        $trinket = Trinket::query()->firstOrFail();

        (new UpdateService(Trinket::class))
            ->standalone(true)
            ->columns(['*label', 'widget.title'])
            ->update($trinket->id, ['label' => 'renamed']);

        $this->assertSame('renamed', $trinket->refresh()->label);
        $this->assertSame('Alpha', $widget->refresh()->title);
    }

    public function test_cascade_deletes_the_children_one_row_at_a_time(): void {
        $widget = $this->widgets();
        $deleted = [];

        $this->trinkets($widget->id, ['a', 'b']);

        Trinket::deleted(function (Trinket $trinket) use (&$deleted): void {
            $deleted[] = $trinket->label;
        });

        (new DeleteService(Widget::class))
            ->standalone(true)
            ->cascade(['trinkets'])
            ->delete(['id' => $widget->id]);

        $this->assertSame(['a', 'b'], $deleted);
        $this->assertSame(0, Trinket::query()->count());
        $this->assertSame(0, Widget::query()->count());
    }

    public function test_cascade_delete_reaches_grandchildren(): void {
        $widget = $this->widgets();
        $child = Trinket::forceCreate(['label' => 'child', 'widget_id' => $widget->id]);

        Trinket::forceCreate(['label' => 'grandchild', 'trinket_id' => $child->id]);

        (new DeleteService(Widget::class))
            ->standalone(true)
            ->cascade(['trinkets.trinkets'])
            ->delete(['id' => $widget->id]);

        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_cascade_delete_reaches_a_morph_relation(): void {
        $widget = $this->widgets();

        Trinket::forceCreate(['label' => 'owned', 'owner_id' => $widget->id, 'owner_type' => Widget::class]);

        (new DeleteService(Widget::class))
            ->standalone(true)
            ->cascade(['owned'])
            ->delete(['id' => $widget->id]);

        $this->assertSame(0, Trinket::query()->count());
    }

    public function test_cascade_delete_refuses_a_relation_it_would_walk_backwards(): void {
        $widget = $this->widgets();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('invalid-cascade-relation');

        (new DeleteService(Widget::class))
            ->standalone(true)
            ->cascade(['tagged'])
            ->delete(['id' => $widget->id]);
    }

    public function test_a_removal_record_excludes_the_joined_columns(): void {
        $trinket = Trinket::forceCreate(['label' => 'pin']);
        $widget = Widget::forceCreate(['title' => 'Alpha', 'trinket_id' => $trinket->id]);

        (new DeleteService(Widget::class))
            ->standalone(true)
            ->columns(['pinned.label'])
            ->delete(['id' => $widget->id]);

        $before = ManipulationLog::query()
            ->where('data_type', 'stub_widget')
            ->where('type', ManipulationType::Deleted)
            ->sole()
            ->before;
        $keys = is_array($before) ? array_keys($before) : [];

        sort($keys);

        $this->assertSame(['ip', 'ranking', 'title', 'trinket_id'], $keys);
    }

    public function test_a_removal_is_recorded_in_the_audit_trail(): void {
        $widget = $this->widgets();
        $before = ManipulationLog::query()->where('data_type', 'stub_widget')->count();

        (new DeleteService(Widget::class))->standalone(true)->delete(['id' => $widget->id]);

        $this->assertSame($before + 1, ManipulationLog::query()->where('data_type', 'stub_widget')->count());
    }

    public function test_a_guard_can_refuse_a_single_record(): void {
        $widget = $this->widgets();

        $this->expectException(ServiceException::class);

        (new GetService(Widget::class))
            ->standalone(true)
            ->columns(['title'])
            ->guard(fn () => error('permission-denied', 403))
            ->get($widget->id);
    }

    public function test_a_nested_service_without_the_route_parameter_is_not_found(): void {
        $this->expectException(ServiceException::class);

        (new ListService(Trinket::class))->columns(['label'])->list([]);
    }

}
