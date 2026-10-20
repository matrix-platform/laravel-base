<?php //>

namespace Tests\Feature\Support\Scaffold;

use Illuminate\Support\Facades\Http;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Scaffold\FieldLabelResolver;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use MatrixPlatform\Support\Scaffold\ScaffoldPlan;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use MatrixPlatform\Support\Subject;
use Tests\FeatureTestCase;
use Tests\Stubs\OkTranslationDriver;

class FieldLabelResolverTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        config(['matrix.locales' => 'tw en']);
    }

    public function test_a_column_comment_is_used_verbatim_for_the_apps_default_locale(): void {
        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), app()->getLocale());

        $this->assertSame('Weight in kilograms', $resolved['labels']['weight']);
        $this->assertSame([], $resolved['notes']);
    }

    public function test_a_field_without_a_comment_gets_no_label_or_note(): void {
        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), app()->getLocale());

        $this->assertArrayNotHasKey('api_token', $resolved['labels']);
    }

    public function test_other_locales_get_a_note_carrying_the_source_text_instead_of_a_translation(): void {
        Http::fake();

        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        OkTranslationDriver::$requestedTargetLocale = null;

        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), 'tw');

        $this->assertArrayNotHasKey('weight', $resolved['labels']);
        $this->assertSame(["Translate the label of 'weight' into locale 'tw' (source: \"Weight in kilograms\"); used a TODO placeholder instead."], $resolved['notes']);
        $this->assertNull(OkTranslationDriver::$requestedTargetLocale);

        Http::assertNothingSent();
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
