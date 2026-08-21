<?php //>

namespace Tests\Feature\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Support\MetadataRegistry;
use ReflectionClass;
use Tests\FeatureTestCase;

class DeclarationTest extends FeatureTestCase {

    /**
     * @return list<class-string<Model>>
     */
    private function declared(): array {
        $models = [];

        foreach (glob(__DIR__ . '/../../../src/Models/*.php') ?: [] as $file) {
            $model = 'MatrixPlatform\Models\\' . basename($file, '.php');

            if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
                continue;
            }

            if ((new ReflectionClass($model))->getAttributes(Declared::class) !== []) {
                $models[] = $model;
            }
        }

        return $models;
    }

    public function test_the_scanner_finds_the_declared_models(): void {
        $this->assertGreaterThanOrEqual(5, count($this->declared()));
    }

    public function test_every_declaration_covers_exactly_its_table(): void {
        foreach ($this->declared() as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $declared = array_keys($definitions);
            $columns = Schema::getColumnListing((new $model())->getTable());

            sort($declared);
            sort($columns);

            $this->assertSame($columns, $declared, $model);
        }
    }

    public function test_every_declared_title_is_a_real_column(): void {
        foreach ($this->declared() as $model) {
            $title = app(MetadataRegistry::class)->of($model)?->title;

            $this->assertNotNull($title, $model);

            $this->assertContains($title, Schema::getColumnListing((new $model())->getTable()), $model);
        }
    }

    public function test_every_password_column_enforces_a_policy_and_stays_optional(): void {
        foreach ($this->declared() as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            if (!array_key_exists('password', $definitions)) {
                continue;
            }

            $rule = $definitions['password']->rule;

            $this->assertInstanceOf(Closure::class, $rule, $model);

            $resolved = $rule();

            $this->assertTrue(Validator::make(['password' => 'secret-Passw0rd'], ['password' => $resolved])->passes(), $model);
            $this->assertFalse(Validator::make(['password' => 'short'], ['password' => $resolved])->passes(), $model);
            $this->assertFalse(Validator::make([], ['password' => array_merge(['present'], $resolved)])->passes(), $model);
            $this->assertTrue(Validator::make(['password' => null], ['password' => $resolved])->passes(), $model);
        }
    }

}
