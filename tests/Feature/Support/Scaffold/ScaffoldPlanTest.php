<?php //>

namespace Tests\Feature\Support\Scaffold;

use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use MatrixPlatform\Support\Scaffold\ScaffoldPlan;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use MatrixPlatform\Support\Subject;
use RuntimeException;
use Tests\FeatureTestCase;

class ScaffoldPlanTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.locales' => 'tw en']);
    }

    public function test_model_and_alias_default_from_the_table_name(): void {
        $plan = $this->build('scaffold_simple');

        $this->assertSame('Simple', $plan->modelName);
        $this->assertSame('simple', $plan->alias);
        $this->assertSame('title', $plan->titleField);
        $this->assertTrue($plan->sortable);
        $this->assertTrue($plan->arrangeable);
        $this->assertSame([], $plan->customFields);
        $this->assertSame('simple', $plan->path);
    }

    public function test_the_declaration_segments_match_the_real_featured_tag_shape(): void {
        $plan = $this->build('scaffold_simple');

        $expected = "            Definitions::primaryKey(),\n"
            . "            Definitions::title(),\n"
            . "            Definitions::schedules(),\n"
            . "            Definitions::ranking(),\n"
            . '            Definitions::auditings()';

        $this->assertSame($expected, $plan->declarationTokens()['segments']);
        $this->assertSame("'simple', enable: 'enable_time', disable: 'disable_time', ranking: 'ranking'", $plan->declarationTokens()['metadata']);
    }

    public function test_the_model_gets_a_casts_method_for_schedules_but_no_relation(): void {
        $plan = $this->build('scaffold_simple');
        $members = $plan->modelTokens()['members'];

        $this->assertStringContainsString('protected function casts(): array', $members);
        $this->assertStringContainsString("'disable_time' => 'datetime'", $members);
        $this->assertStringNotContainsString('BelongsTo', $members);
    }

    public function test_a_parent_option_produces_a_nested_path_matching_the_real_faq_shape(): void {
        $this->declareParent();

        $plan = $this->build('scaffold_widget', parent: 'parent', alias: 'items');

        $this->assertSame('scaffold-parent/{parent_id}/items', $plan->path);
        $this->assertIsArray($plan->parent);
        $this->assertSame('scaffold-parent', $plan->parent['alias']);
        $this->assertStringContainsString('public function parent(): BelongsTo', $plan->modelTokens()['members']);
        $this->assertStringContainsString("'parent_id'", $plan->declarationTokens()['segments']);
    }

    public function test_a_parent_pointing_to_an_undeclared_model_is_rejected(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/parent-model-not-found/');

        $this->build('scaffold_widget', parent: 'parent');
    }

    public function test_heuristics_are_reflected_in_presentations_and_pending_notes(): void {
        $plan = $this->build('scaffold_widget', title: 'code');

        $this->assertSame(Presentation::Password, $plan->presentations['password']['presentation']);
        $this->assertSame(Presentation::Hidden, $plan->presentations['api_token']['presentation']);
        $this->assertSame(Presentation::Hidden, $plan->presentations['secret_key']['presentation']);
        $this->assertNull($plan->presentations['weight']['presentation']);

        $notes = implode("\n", $plan->pendingNotes);

        $this->assertStringContainsString("HIGH RISK: 'api_token'", $notes);
        $this->assertStringContainsString("HIGH RISK: 'secret_key'", $notes);
        $this->assertStringContainsString("Could not guess a presentation for 'weight'", $notes);
        $this->assertStringContainsString('Composite unique constraint', $notes);
    }

    public function test_a_translatable_field_missing_a_locale_column_is_flagged(): void {
        config(['matrix.locales' => 'tw en jp']);

        $plan = $this->build('scaffold_widget', title: 'code');

        $this->assertStringContainsString("Translatable field 'title' is missing column(s) for locale(s): jp", implode("\n", $plan->pendingNotes));
    }

    public function test_an_explicit_title_option_is_honored_and_validated(): void {
        $plan = $this->build('scaffold_widget', title: 'code');

        $this->assertSame('code', $plan->titleField);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/title-column-not-found/');

        $this->build('scaffold_widget', title: 'not_a_real_column');
    }

    private function build(string $table, ?string $model = null, ?string $title = null, ?string $parent = null, ?string $alias = null): ScaffoldPlan {
        return ScaffoldPlan::build(
            app(SchemaIntrospector::class),
            app(PresentationGuesser::class),
            app(MetadataRegistry::class),
            app(PackageRegistry::class),
            app(Subject::class),
            $table,
            $model,
            $title,
            $parent,
            $alias,
            'App'
        );
    }

    private function declareParent(): void {
        require_once __DIR__ . '/../../../fixtures/package-scaffold/app/Models/Declarations/ScaffoldParentDeclaration.php';
        require_once __DIR__ . '/../../../fixtures/package-scaffold/app/Models/ScaffoldParent.php';

        app(PackageRegistry::class)->register('scaffold-fixture', __DIR__ . '/../../../fixtures/package-scaffold');

        config(['matrix.packages' => 'scaffold-fixture']);
    }

}
