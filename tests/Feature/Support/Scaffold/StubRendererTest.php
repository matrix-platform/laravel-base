<?php //>

namespace Tests\Feature\Support\Scaffold;

use Illuminate\Support\Facades\Process;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use MatrixPlatform\Support\Scaffold\ScaffoldPlan;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use MatrixPlatform\Support\Scaffold\StubRenderer;
use MatrixPlatform\Support\Subject;
use Tests\FeatureTestCase;

class StubRendererTest extends FeatureTestCase {

    private const STUBS = __DIR__ . '/../../../../resources/stubs/scaffold';

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.locales' => 'tw en']);
    }

    public function test_token_placeholders_are_replaced(): void {
        $renderer = new StubRenderer();
        $stub = tempnam(sys_get_temp_dir(), 'stub');
        file_put_contents($stub, "<?php //>\n\nnamespace {{ namespace }};\n");

        $content = $renderer->render($stub, ['namespace' => 'App\\Models']);

        unlink($stub);

        $this->assertSame("<?php //>\n\nnamespace App\\Models;\n", $content);
    }

    public function test_the_four_stubs_render_valid_and_semantically_equivalent_output_for_a_simple_resource(): void {
        $plan = ScaffoldPlan::build(
            app(SchemaIntrospector::class),
            app(PresentationGuesser::class),
            app(MetadataRegistry::class),
            app(PackageRegistry::class),
            app(Subject::class),
            'scaffold_simple',
            null,
            null,
            null,
            null,
            'App'
        );
        $renderer = new StubRenderer();

        $model = $renderer->render(self::STUBS . '/model.stub', $plan->modelTokens());
        $declaration = $renderer->render(self::STUBS . '/declaration.stub', $plan->declarationTokens());
        $controller = $renderer->render(self::STUBS . '/controller.stub', $plan->controllerTokens());
        $i18n = $renderer->render(self::STUBS . '/model-i18n.stub', $plan->modelI18nTokens([]));

        $this->assertValidPhp($model);
        $this->assertValidPhp($declaration);
        $this->assertValidPhp($controller);
        $this->assertValidPhp($i18n);

        $this->assertStringContainsString('namespace App\\Models;', $model);
        $this->assertStringContainsString('class Simple extends BaseModel {', $model);
        $this->assertStringContainsString("protected \$table = 'scaffold_simple';", $model);
        $this->assertStringContainsString('Definitions::title()', $declaration);
        $this->assertStringContainsString('Definitions::schedules()', $declaration);
        $this->assertStringContainsString('protected string $model = Simple::class;', $controller);
        $this->assertSame("<?php //>\n\nreturn [\n\n];\n", $i18n);
    }

    private function assertValidPhp(string $code): void {
        $path = tempnam(sys_get_temp_dir(), 'scaffold') . '.php';
        file_put_contents($path, $code);

        $result = Process::run(['php', '-l', $path]);

        unlink($path);

        $this->assertTrue($result->successful(), $result->errorOutput());
    }

}
