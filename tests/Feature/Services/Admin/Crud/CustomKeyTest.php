<?php //>

namespace Tests\Feature\Services\Admin\Crud;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Services\Admin\Crud\ArrangeService;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use MatrixPlatform\Services\Admin\Crud\ExportService;
use MatrixPlatform\Services\Admin\Crud\GetService;
use MatrixPlatform\Services\Admin\Crud\ListService;
use MatrixPlatform\Services\Admin\Crud\SortService;
use MatrixPlatform\Services\Admin\Crud\UpdateService;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\Keyed;
use Tests\Stubs\StubDeclaration;

class CustomKeyTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();

        Schema::create('stub_keyed', function (Blueprint $table): void {
            $table->integer('code')->primary();
            $table->string('title');
            $table->integer('ranking')->default(0);
            $table->timestamp('enable_time')->nullable();
            $table->timestamp('disable_time')->nullable();
            $table->integer('creator_id')->nullable();
            $table->timestamp('create_time')->nullable();
            $table->integer('updater_id')->nullable();
            $table->timestamp('update_time')->nullable();
        });

        app(MetadataRegistry::class)->register(Keyed::class, new StubDeclaration(new Metadata('keyed', 'title')));
    }

    private function keyed(int $code, string $title): Keyed {
        return Keyed::forceCreate(['code' => $code, 'title' => $title]);
    }

    public function test_an_update_keeping_its_own_unique_value_passes(): void {
        app(MetadataRegistry::class)->register(Keyed::class, new StubDeclaration(new Metadata('keyed', 'title'), ['title' => Definition::text(required: true, unique: true)]));

        $this->keyed(1, 'first');

        (new UpdateService(Keyed::class))
            ->standalone(true)
            ->columns(['title'])
            ->update(1, ['title' => 'first']);

        $this->assertSame('first', Keyed::query()->findOrFail(1)->title);
    }

    public function test_an_update_taking_another_rows_unique_value_is_rejected(): void {
        app(MetadataRegistry::class)->register(Keyed::class, new StubDeclaration(new Metadata('keyed', 'title'), ['title' => Definition::text(required: true, unique: true)]));

        $this->keyed(1, 'first');
        $this->keyed(2, 'second');

        try {
            (new UpdateService(Keyed::class))
                ->standalone(true)
                ->columns(['title'])
                ->update(1, ['title' => 'second']);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('Unique', $exception->validator->failed()['title']);

            return;
        }

        $this->fail('the update was expected to be rejected');
    }

    public function test_delete_finds_the_rows_by_the_model_key(): void {
        $this->keyed(1, 'kept');
        $this->keyed(2, 'gone');

        (new DeleteService(Keyed::class))
            ->standalone(true)
            ->delete(['id' => [2]]);

        $this->assertSame(['kept'], Keyed::query()->pluck('title')->all());
    }

    public function test_export_selects_and_orders_the_rows_by_the_model_key(): void {
        $this->keyed(3, 'third');
        $this->keyed(1, 'first');
        $this->keyed(2, 'second');

        $rows = (new ExportService(Keyed::class))
            ->standalone(true)
            ->columns(['title'])
            ->export(['id' => [3, 1]])['rows'];

        $this->assertSame(['first', 'third'], array_column($rows, 'title'));
    }

    public function test_list_selects_the_model_key(): void {
        $this->keyed(1, 'first');

        $rows = (new ListService(Keyed::class))
            ->standalone(true)
            ->columns(['title'])
            ->list([])['rows'];

        $this->assertSame(1, $rows[0]['code']);
    }

    public function test_sort_breaks_ranking_ties_by_the_model_key(): void {
        $this->keyed(2, 'second');
        $this->keyed(1, 'first');

        $rows = (new SortService(Keyed::class))->standalone(true)->items()['rows'];

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function test_arrange_breaks_ranking_ties_by_the_model_key(): void {
        $this->keyed(2, 'second');
        $this->keyed(1, 'first');

        $rows = (new ArrangeService(Keyed::class))->standalone(true)->items()['rows'];

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function test_get_includes_the_model_key_in_the_data(): void {
        $this->keyed(5, 'fifth');

        $data = (new GetService(Keyed::class))
            ->standalone(true)
            ->columns(['title'])
            ->get(5)['data'];

        $this->assertSame(5, $data['code']);
    }

}
