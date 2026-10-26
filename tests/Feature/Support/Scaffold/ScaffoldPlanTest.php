<?php //>

namespace Tests\Feature\Support\Scaffold;

use Illuminate\Support\Facades\DB;
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

    public function test_a_non_translatable_title_column_is_declared_as_a_plain_text_field(): void {
        $plan = $this->build('scaffold_plain');
        $segments = $plan->declarationTokens()['segments'];

        $this->assertSame('title', $plan->titleField);
        $this->assertSame(['title'], $plan->customFields);
        $this->assertStringContainsString("'title' => Definition::text(", $segments);
        $this->assertStringNotContainsString('Definitions::title()', $segments);
    }

    public function test_not_null_columns_are_required(): void {
        $plan = $this->build('scaffold_required');
        $segments = $plan->declarationTokens()['segments'];

        $this->assertStringContainsString("'label' => Definition::text(required: true)", $segments);
        $this->assertStringContainsString("'active' => Definition::boolean(Presentation::Switch, required: true)", $segments);
        $this->assertStringContainsString("'qty' => Definition::integer()", $segments);
        $this->assertStringContainsString("'tags' => Definition::json(required: true)", $segments);
        $this->assertStringContainsString("'tags' is a NOT NULL JSON column", implode("\n", $plan->pendingNotes));
    }

    public function test_the_parent_foreign_key_is_never_required(): void {
        $this->declareParent();

        $segments = $this->build('scaffold_child', parent: 'parent', alias: 'items')->declarationTokens()['segments'];

        $this->assertStringContainsString("'label' => Definition::text(required: true)", $segments);
        $this->assertStringContainsString("'parent_id' => Definition::integer()", $segments);
    }

    public function test_the_model_gets_a_casts_method_for_schedules_but_no_relation(): void {
        $plan = $this->build('scaffold_simple');
        $members = $plan->modelTokens()['members'];

        $this->assertStringContainsString('protected function casts(): array', $members);
        $this->assertStringContainsString("'disable_time' => 'datetime'", $members);
        $this->assertStringNotContainsString('BelongsTo', $members);
    }

    public function test_an_explicit_title_option_is_honored_and_validated(): void {
        $plan = $this->build('scaffold_widget', title: 'code');

        $this->assertSame('code', $plan->titleField);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/title-column-not-found/');

        $this->build('scaffold_widget', title: 'not_a_real_column');
    }

    public function test_an_explicit_model_name_drives_the_alias_and_the_declaration(): void {
        $plan = $this->build('scaffold_simple', model: 'FeaturedTag');

        $this->assertSame('FeaturedTag', $plan->modelName);
        $this->assertSame('FeaturedTagDeclaration', $plan->declarationName);
        $this->assertSame('featured-tag', $plan->alias);
        $this->assertSame('featured-tag', $plan->path);
    }

    public function test_the_title_falls_back_to_name_and_then_to_the_first_required_text_column(): void {
        DB::statement('CREATE TABLE scaffold_named (id integer PRIMARY KEY, code text NOT NULL, name text)');
        DB::statement('CREATE TABLE scaffold_unnamed (id integer PRIMARY KEY, note text, code text NOT NULL, label text NOT NULL)');

        $this->assertSame('name', $this->build('scaffold_named')->titleField);
        $this->assertSame('code', $this->build('scaffold_unnamed')->titleField);
    }

    public function test_a_table_without_any_title_candidate_is_rejected(): void {
        DB::statement('CREATE TABLE scaffold_untitled (id integer PRIMARY KEY, note text, qty integer NOT NULL)');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/title-not-guessable/');

        $this->build('scaffold_untitled');
    }

    public function test_a_parent_column_without_a_foreign_key_is_rejected(): void {
        DB::statement('CREATE TABLE scaffold_orphan (id integer PRIMARY KEY, name text NOT NULL, owner_id integer)');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/parent-foreign-key-not-found: .*'owner_id'/");

        $this->build('scaffold_orphan', parent: 'owner');
    }

    public function test_a_creator_without_an_updater_is_declared_as_auditings_without_updates(): void {
        DB::statement('CREATE TABLE scaffold_log (id integer PRIMARY KEY, name text NOT NULL, creator_id integer, create_time timestamp NOT NULL)');

        $segments = $this->build('scaffold_log')->declarationTokens()['segments'];

        $this->assertStringEndsWith('Definitions::auditings(false)', $segments);
    }

    public function test_a_unique_translatable_title_is_declared_as_a_unique_title(): void {
        DB::statement('CREATE TABLE scaffold_tag (id integer PRIMARY KEY, title__tw text UNIQUE, title__en text)');

        $this->assertStringContainsString('Definitions::title(true)', $this->build('scaffold_tag')->declarationTokens()['segments']);
    }

    public function test_date_and_time_columns_are_cast_and_typed_as_carbon(): void {
        DB::statement('CREATE TABLE scaffold_event (id integer PRIMARY KEY, name text NOT NULL, held_on date, seen_at timestamptz, open boolean NOT NULL)');

        $tokens = $this->build('scaffold_event')->modelTokens();

        $this->assertStringContainsString('use Illuminate\\Support\\Carbon;', $tokens['uses']);
        $this->assertStringContainsString(' * @property ?Carbon $held_on', $tokens['properties']);
        $this->assertStringContainsString(' * @property ?Carbon $seen_at', $tokens['properties']);
        $this->assertStringContainsString(' * @property bool $open', $tokens['properties']);
        $this->assertStringContainsString("'held_on' => 'date'", $tokens['members']);
        $this->assertStringContainsString("'seen_at' => 'datetime'", $tokens['members']);
    }

    public function test_a_label_is_escaped_into_a_php_string_literal(): void {
        $entries = $this->build('scaffold_plain')->modelI18nTokens(['title' => "It's C:\\temp"])['entries'];

        $this->assertSame("\n    'title' => 'It\\'s C:\\\\temp',\n", $entries);
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
