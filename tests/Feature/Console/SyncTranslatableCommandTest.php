<?php //>

namespace Tests\Feature\Console;

use App\Models\Probe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Models\AnotherProbe;
use MatrixPlatform\Support\Metadata;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use Tests\FeatureTestCase;
use Tests\Stubs\StubDeclaration;

class SyncTranslatableCommandTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        require_once __DIR__ . '/../../fixtures/package-translatable/app/Models/Probe.php';
        require_once __DIR__ . '/../../fixtures/package-translatable/src/Models/AnotherProbe.php';

        app(PackageRegistry::class)->register('probe-package', __DIR__ . '/../../fixtures/package-translatable');

        config(['matrix.packages' => 'probe-package']);

        $this->declare('App\\Models\\Probe', 'translated');
        $this->declare('MatrixPlatform\\Models\\AnotherProbe', 'translated');

        Probe::forceCreate(['id' => 1, 'translated__tw' => 'Alpha', 'translated__en' => 'Beta']);
        AnotherProbe::forceCreate(['id' => 2, 'translated__tw' => 'Gamma', 'translated__en' => 'Delta']);
    }

    private function declare(string $model, string $field): void {
        app(MetadataRegistry::class)->register($model, new StubDeclaration(new Metadata('probe'), [
            $field => Definition::text(translatable: true)
        ]));
    }

    public function test_a_new_locale_gets_a_column_on_every_translatable_field_found_across_both_namespaces(): void {
        config(['matrix.locales' => 'tw en fr']);

        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('stub_widget', 'translated__fr'));
        $this->assertTrue(Schema::hasColumn('stub_gadget', 'translated__fr'));
    }

    public function test_existing_locale_values_are_left_untouched_and_the_new_column_has_no_backfill(): void {
        config(['matrix.locales' => 'tw en fr']);

        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);

        $widget = Probe::query()->findOrFail(1);

        $this->assertSame('Alpha', $widget->getAttribute('translated__tw'));
        $this->assertSame('Beta', $widget->getAttribute('translated__en'));
        $this->assertNull($widget->getAttribute('translated__fr'));
    }

    public function test_the_new_column_copies_the_type_of_an_existing_locale_column(): void {
        config(['matrix.locales' => 'tw en fr']);

        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);

        $type = DB::table('information_schema.columns')
            ->where('table_name', 'stub_widget')
            ->where('column_name', 'translated__fr')
            ->value('data_type');

        $this->assertSame('text', $type);
    }

    public function test_running_the_command_twice_does_not_fail_or_duplicate_the_column(): void {
        config(['matrix.locales' => 'tw en fr']);

        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);
        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('stub_widget', 'translated__fr'));
    }

    public function test_a_locale_that_already_has_its_column_is_left_alone(): void {
        $before = Probe::query()->findOrFail(1)->getAttributes();

        $this->artisanCommand('matrix:sync-translatable')->assertExitCode(0);

        $after = Probe::query()->findOrFail(1)->getAttributes();

        $this->assertSame($before, $after);
    }

    public function test_a_field_without_any_existing_locale_column_is_skipped_with_a_warning(): void {
        $this->declare('App\\Models\\Probe', 'ghost');

        $this->artisanCommand('matrix:sync-translatable')
            ->expectsOutputToContain('Skipping stub_widget.ghost')
            ->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('stub_widget', 'ghost__tw'));
    }

}
