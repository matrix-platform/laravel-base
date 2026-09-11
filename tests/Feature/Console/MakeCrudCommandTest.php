<?php //>

namespace Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use MatrixPlatform\Support\PackageRegistry;
use Tests\FeatureTestCase;

class MakeCrudCommandTest extends FeatureTestCase {

    private string $root;

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.locales' => 'tw en']);

        $this->root = sys_get_temp_dir() . '/make-crud-test-' . Str::random(12);

        app(Filesystem::class)->ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void {
        app(Filesystem::class)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_simple_resource_produces_four_files_matching_the_featured_tag_shape(): void {
        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root)->assertExitCode(0);

        $modelPath = "{$this->root}/app/Models/Simple.php";
        $declarationPath = "{$this->root}/app/Models/Declarations/SimpleDeclaration.php";
        $controllerPath = "{$this->root}/app/Http/Controllers/Admin/SimpleController.php";
        $twPath = "{$this->root}/resources/i18n/tw/model/scaffold_simple.php";
        $enPath = "{$this->root}/resources/i18n/en/model/scaffold_simple.php";

        foreach ([$modelPath, $declarationPath, $controllerPath, $twPath, $enPath] as $path) {
            $this->assertFileExists($path);
            $this->assertValidPhpFile($path);
        }

        $model = $this->read($modelPath);

        $this->assertStringContainsString('namespace App\\Models;', $model);
        $this->assertStringContainsString('class Simple extends BaseModel {', $model);
        $this->assertStringContainsString("protected \$table = 'scaffold_simple';", $model);
        $this->assertStringContainsString('protected function casts(): array', $model);
        $this->assertStringNotContainsString('BelongsTo', $model);

        $declaration = $this->read($declarationPath);

        $this->assertStringContainsString('Definitions::primaryKey()', $declaration);
        $this->assertStringContainsString('Definitions::title()', $declaration);
        $this->assertStringContainsString('Definitions::schedules()', $declaration);
        $this->assertStringContainsString('Definitions::ranking()', $declaration);
        $this->assertStringContainsString('Definitions::auditings()', $declaration);
        $this->assertStringContainsString("new Metadata('simple', enable: 'enable_time', disable: 'disable_time', ranking: 'ranking');", $declaration);

        $controller = $this->read($controllerPath);

        $this->assertStringContainsString('class SimpleController extends CrudController {', $controller);
        $this->assertStringContainsString('protected string $model = Simple::class;', $controller);

        $this->assertSame("<?php //>\n\nreturn [\n\n];\n", $this->read($twPath));
        $this->assertSame("<?php //>\n\nreturn [\n\n];\n", $this->read($enPath));
    }

    public function test_a_parent_option_produces_the_nested_faq_style_shape(): void {
        $this->declareParent();

        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root . ' --parent=parent --alias=items --title=code')
            ->expectsOutputToContain("ActionRoutes::mount('scaffold-parent/{parent_id}/items', WidgetController::class);")
            ->assertExitCode(0);

        $model = $this->read("{$this->root}/app/Models/Widget.php");

        $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;', $model);
        $this->assertStringContainsString('public function parent(): BelongsTo {', $model);
        $this->assertStringContainsString("return \$this->belongsTo(ScaffoldParent::class, 'parent_id');", $model);

        $declaration = $this->read("{$this->root}/app/Models/Declarations/WidgetDeclaration.php");

        $this->assertStringContainsString("'parent_id' => Definition::integer()", $declaration);
        $this->assertStringContainsString("new Metadata('items', 'code', 'parent', enable: 'enable_time', disable: 'disable_time', ranking: 'ranking');", $declaration);
    }

    public function test_a_parent_pointing_to_an_undeclared_model_fails_without_writing_any_file(): void {
        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root . ' --parent=parent')
            ->expectsOutputToContain('parent-model-not-found')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist("{$this->root}/app");
    }

    public function test_a_translatable_field_missing_a_locale_is_flagged_in_the_pending_list(): void {
        config(['matrix.locales' => 'tw en jp']);

        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root . ' --title=code')
            ->expectsOutputToContain("Translatable field 'title' is missing column(s) for locale(s): jp")
            ->assertExitCode(0);
    }

    public function test_unique_constraints_are_reflected_and_composite_ones_are_flagged(): void {
        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root . ' --title=code')
            ->expectsOutputToContain('Composite unique constraint')
            ->assertExitCode(0);

        $declaration = $this->read("{$this->root}/app/Models/Declarations/WidgetDeclaration.php");

        $this->assertStringContainsString("'code' => Definition::text(unique: true)", $declaration);
    }

    public function test_heuristics_hit_and_miss_as_expected(): void {
        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root . ' --title=code')
            ->expectsOutputToContain("HIGH RISK: 'api_token'")
            ->expectsOutputToContain("HIGH RISK: 'secret_key'")
            ->expectsOutputToContain("Could not guess a presentation for 'weight'")
            ->assertExitCode(0);

        $declaration = $this->read("{$this->root}/app/Models/Declarations/WidgetDeclaration.php");

        $this->assertStringContainsString("'password' => Definition::text(Presentation::Password)", $declaration);
        $this->assertStringContainsString("'api_token' => Definition::text(Presentation::Hidden)", $declaration);
        $this->assertStringContainsString("'secret_key' => Definition::text(Presentation::Hidden)", $declaration);
        $this->assertStringContainsString("'weight' => Definition::float()", $declaration);
    }

    public function test_an_existing_file_is_not_overwritten_without_force_but_force_overwrites_it(): void {
        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root)->assertExitCode(0);

        $modelPath = "{$this->root}/app/Models/Simple.php";
        $original = $this->read($modelPath);

        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root . ' --namespace=Foo')
            ->expectsOutputToContain("Already exists, not overwritten (pass --force to overwrite): {$modelPath}")
            ->assertExitCode(0);

        $this->assertSame($original, $this->read($modelPath));

        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root . ' --namespace=Foo --force')
            ->assertExitCode(0);

        $this->assertNotSame($original, $this->read($modelPath));
    }

    public function test_dry_run_creates_no_files_at_all(): void {
        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root . ' --dry-run')
            ->expectsOutputToContain('dry-run, not written')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist("{$this->root}/app");
        $this->assertDirectoryDoesNotExist("{$this->root}/resources");
    }

    public function test_routes_menu_and_menu_i18n_are_only_printed_never_written(): void {
        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root)
            ->expectsOutputToContain("ActionRoutes::mount('simple', SimpleController::class);")
            ->expectsOutputToContain("'simple' => [")
            ->expectsOutputToContain("'simple' => 'TODO: Simple list title',")
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist("{$this->root}/routes");
        $this->assertDirectoryDoesNotExist("{$this->root}/resources/menu");
        $this->assertDirectoryDoesNotExist("{$this->root}/resources/i18n/tw/menu");
    }

    public function test_the_locale_option_limits_which_i18n_files_are_produced(): void {
        $this->artisanCommand('matrix:make-crud scaffold_simple --path=' . $this->root . ' --locale=tw')->assertExitCode(0);

        $this->assertFileExists("{$this->root}/resources/i18n/tw/model/scaffold_simple.php");
        $this->assertFileDoesNotExist("{$this->root}/resources/i18n/en/model/scaffold_simple.php");
    }

    public function test_a_column_comment_becomes_the_label_for_the_apps_default_locale_and_is_flagged_elsewhere(): void {
        config()->set('matrix.translation-provider', 'does-not-exist');

        $this->artisanCommand('matrix:make-crud scaffold_widget --path=' . $this->root)
            ->expectsOutputToContain("Could not auto-translate the comment for 'weight' into locale 'tw'; used a TODO placeholder instead.")
            ->assertExitCode(0);

        $this->assertStringContainsString("'weight' => 'Weight in kilograms',", $this->read("{$this->root}/resources/i18n/en/model/scaffold_widget.php"));
        $this->assertStringContainsString("'weight' => 'TODO: weight',", $this->read("{$this->root}/resources/i18n/tw/model/scaffold_widget.php"));
    }

    private function assertValidPhpFile(string $path): void {
        $result = Process::run(['php', '-l', $path]);

        $this->assertTrue($result->successful(), $result->errorOutput());
    }

    private function declareParent(): void {
        require_once __DIR__ . '/../../fixtures/package-scaffold/app/Models/Declarations/ScaffoldParentDeclaration.php';
        require_once __DIR__ . '/../../fixtures/package-scaffold/app/Models/ScaffoldParent.php';

        app(PackageRegistry::class)->register('scaffold-fixture', __DIR__ . '/../../fixtures/package-scaffold');

        config(['matrix.packages' => 'scaffold-fixture']);
    }

    private function read(string $path): string {
        $content = file_get_contents($path);

        if ($content === false) {
            $this->fail("could not read {$path}");
        }

        return $content;
    }

}
