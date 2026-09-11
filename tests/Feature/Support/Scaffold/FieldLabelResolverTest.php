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

    public function test_the_comment_is_translated_into_other_locales_when_a_driver_is_configured(): void {
        $this->useTranslationFixtures();

        config()->set('matrix.translation-provider', 'stub');

        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), 'tw');

        $this->assertSame('translated: Weight in kilograms', $resolved['labels']['weight']);
        $this->assertSame('en', OkTranslationDriver::$requestedSourceLocale);
        $this->assertSame('tw', OkTranslationDriver::$requestedTargetLocale);
        $this->assertSame([], $resolved['notes']);
    }

    public function test_translation_falls_back_to_a_note_when_no_driver_is_configured(): void {
        config()->set('matrix.translation-provider', 'does-not-exist');

        $resolved = app(FieldLabelResolver::class)->resolve($this->build('scaffold_widget'), 'tw');

        $this->assertArrayNotHasKey('weight', $resolved['labels']);
        $this->assertSame(["Could not auto-translate the comment for 'weight' into locale 'tw'; used a TODO placeholder instead."], $resolved['notes']);
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
