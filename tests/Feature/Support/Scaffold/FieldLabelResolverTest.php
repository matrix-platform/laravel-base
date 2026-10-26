<?php //>

namespace Tests\Feature\Support\Scaffold;

use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Scaffold\FieldLabelResolver;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use MatrixPlatform\Support\Scaffold\ScaffoldPlan;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use MatrixPlatform\Support\Subject;
use Tests\FeatureTestCase;

class FieldLabelResolverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.locales' => 'tw en']);
    }

    public function test_only_a_commented_field_gets_its_comment_verbatim_for_the_apps_default_locale(): void {
        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), app()->getLocale());

        $this->assertSame(['weight' => 'Weight in kilograms'], $resolved['labels']);
        $this->assertSame([], $resolved['notes']);
    }

    public function test_other_locales_get_a_note_carrying_the_source_text_instead_of_a_translation(): void {
        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), 'tw');

        $this->assertSame([], $resolved['labels']);
        $this->assertSame(["Translate the label of 'weight' into locale 'tw' (source: \"Weight in kilograms\"); used a TODO placeholder instead."], $resolved['notes']);
    }

    private function build(string $table): ScaffoldPlan {
        return ScaffoldPlan::build(
            app(SchemaIntrospector::class),
            app(PresentationGuesser::class),
            app(MetadataRegistry::class),
            app(PackageRegistry::class),
            app(Subject::class),
            $table,
            null,
            'code',
            null,
            null,
            'App'
        );
    }

}
