<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Validation\ValidationException;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\InsertService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\NewService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;
use Tests\Stubs\Widget;

class TranslatableTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();
        $this->declare();
    }

    private function declare(): void {
        app(MetadataRegistry::class)->register(Widget::class, new StubDeclaration(new Metadata('widget'), [
            'translated' => Definition::text(translatable: true)
        ]));
    }

    public function test_rules_require_every_locale_for_a_required_translatable_column(): void {
        try {
            (new InsertService(Widget::class))
                ->standalone(true)
                ->columns(['*translated'])
                ->insert([]);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->failed();

            $this->assertArrayHasKey('Required', $errors['translated__tw']);
            $this->assertArrayHasKey('Required', $errors['translated__en']);
            $this->assertArrayNotHasKey('translated', $errors);

            return;
        }

        $this->fail('the insert was expected to be rejected');
    }

    public function test_rules_require_the_presence_of_every_locale_for_an_optional_translatable_column(): void {
        try {
            (new InsertService(Widget::class))
                ->standalone(true)
                ->columns(['translated'])
                ->insert([]);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->failed();

            $this->assertArrayHasKey('Present', $errors['translated__tw']);
            $this->assertArrayHasKey('Present', $errors['translated__en']);

            return;
        }

        $this->fail('the insert was expected to be rejected');
    }

    public function test_insert_writes_the_locale_values_sent_in_the_request(): void {
        $id = (new InsertService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->insert(['translated__tw' => 'Alpha', 'translated__en' => 'Beta'])['id'];

        $widget = Widget::query()->findOrFail(intval($id));

        $this->assertSame('Alpha', $widget->translated__tw);
        $this->assertSame('Beta', $widget->translated__en);
    }

    public function test_a_readonly_translatable_column_never_takes_the_input(): void {
        $id = (new InsertService(Widget::class))
            ->standalone(true)
            ->columns(['*title', '!translated'])
            ->insert(['title' => 'Alpha', 'translated__tw' => 'Alpha', 'translated__en' => 'Beta'])['id'];

        $widget = Widget::query()->findOrFail(intval($id));

        $this->assertNull($widget->translated__tw);
        $this->assertNull($widget->translated__en);
    }

    public function test_update_writes_the_locale_values_sent_in_the_request(): void {
        $widget = Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        (new UpdateService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->update($widget->id, ['translated__tw' => 'Gamma', 'translated__en' => 'Beta']);

        $updated = Widget::query()->findOrFail($widget->id);

        $this->assertSame('Gamma', $updated->translated__tw);
        $this->assertSame('Beta', $updated->translated__en);
    }

    public function test_get_returns_the_collapsed_value_alongside_every_locale(): void {
        $widget = Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $data = (new GetService(Widget::class))->standalone(true)->columns(['translated'])->get($widget->id)['data'];

        $this->assertSame('Beta', $data['translated']);
        $this->assertSame('Alpha', $data['translated__tw']);
        $this->assertSame('Beta', $data['translated__en']);
    }

    public function test_new_reports_a_blank_value_for_the_collapsed_name_and_every_locale(): void {
        $data = (new NewService(Widget::class))->standalone(true)->columns(['translated'])->new()['data'];

        $this->assertArrayHasKey('translated', $data);
        $this->assertNull($data['translated']);
        $this->assertNull($data['translated__tw']);
        $this->assertNull($data['translated__en']);
    }

    public function test_the_column_shape_reports_whether_a_column_is_translatable(): void {
        $columns = (new ListService(Widget::class))->standalone(true)->columns(['title', 'translated'])->list([])['columns'];

        $this->assertFalse($columns[0]['translatable']);
        $this->assertTrue($columns[1]['translatable']);
    }

    public function test_list_rows_carry_the_collapsed_value_alongside_every_locale(): void {
        Widget::forceCreate(['translated__tw' => 'Alpha', 'translated__en' => 'Beta']);

        $rows = (new ListService(Widget::class))->standalone(true)->columns(['translated'])->list([])['rows'];

        $this->assertSame('Beta', $rows[0]['translated']);
        $this->assertSame('Alpha', $rows[0]['translated__tw']);
        $this->assertSame('Beta', $rows[0]['translated__en']);
    }

    public function test_sorting_by_the_abstract_name_sorts_on_the_current_locale_column(): void {
        Widget::forceCreate(['translated__en' => 'Banana']);
        Widget::forceCreate(['translated__en' => 'Apple']);

        $rows = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->list(['sort' => [['name' => 'translated', 'direction' => 'asc']]])['rows'];

        $this->assertSame(['Apple', 'Banana'], array_column($rows, 'translated'));
    }

    public function test_filtering_by_the_abstract_name_filters_on_the_current_locale_column(): void {
        Widget::forceCreate(['translated__en' => 'Banana']);
        Widget::forceCreate(['translated__en' => 'Apple']);

        $rows = (new ListService(Widget::class))
            ->standalone(true)
            ->columns(['translated'])
            ->list(['filters' => ['translated' => ['op' => 'contains', 'value' => 'App']]])['rows'];

        $this->assertSame(['Apple'], array_column($rows, 'translated'));
    }

}
