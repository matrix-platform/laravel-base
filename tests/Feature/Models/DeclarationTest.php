<?php //>

namespace Tests\Feature\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\File;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\MemberLog;
use MatrixPlatform\Models\PushLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\TelegramLog;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\Vendor;
use MatrixPlatform\Models\VendorLog;
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

            $declared = [];

            foreach ($definitions as $field => $definition) {
                array_push($declared, ...$this->columnsFor($field, $definition));
            }

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

            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $definition = array_get_value($definitions, $title);

            $this->assertInstanceOf(Definition::class, $definition, $model);

            $columns = Schema::getColumnListing((new $model())->getTable());

            foreach ($this->columnsFor($title, $definition) as $column) {
                $this->assertContains($column, $columns, $model);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function columnsFor(string $field, Definition $definition): array {
        if (!$definition->translatable) {
            return [$field];
        }

        return array_map(fn (string $locale): string => "{$field}__{$locale}", locales());
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

    public function test_every_login_username_is_required_and_unique(): void {
        foreach ([User::class, Member::class, Vendor::class] as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $definition = array_get_value($definitions, 'username');

            $this->assertInstanceOf(Definition::class, $definition, $model);
            $this->assertTrue($definition->required, $model);
            $this->assertTrue($definition->unique, $model);
        }
    }

    public function test_every_account_status_is_required(): void {
        foreach ([Member::class, Vendor::class] as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $definition = array_get_value($definitions, 'status');

            $this->assertInstanceOf(Definition::class, $definition, $model);
            $this->assertTrue($definition->required, $model);
        }
    }

    public function test_every_not_null_column_without_a_default_is_declared_required(): void {
        $written = [AuthToken::class, File::class, MailLog::class, ManipulationLog::class, MemberLog::class, PushLog::class, SmsLog::class, TelegramLog::class, UserLog::class, VendorLog::class];

        foreach (array_diff($this->declared(), $written) as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $fields = [];

            foreach ($definitions as $field => $definition) {
                foreach ($this->columnsFor($field, $definition) as $column) {
                    $fields[$column] = $definition;
                }
            }

            $table = (new $model())->getTable();
            $foreign = array_merge(...array_map(fn (array $key): array => $key['columns'], Schema::getForeignKeys($table)));

            foreach (Schema::getColumns($table) as $column) {
                if ($column['nullable'] || $column['default'] !== null || $column['name'] === 'create_time' || in_array($column['name'], $foreign, true)) {
                    continue;
                }

                $this->assertArrayHasKey($column['name'], $fields, "{$model}::{$column['name']}");
                $this->assertTrue($fields[$column['name']]->required, "{$model}::{$column['name']} is NOT NULL without a default but not declared required");
            }
        }
    }

    public function test_every_unique_column_is_declared_unique(): void {
        foreach ($this->declared() as $model) {
            $definitions = app(MetadataRegistry::class)->definitions($model);

            $this->assertNotNull($definitions, $model);

            $fields = [];

            foreach ($definitions as $field => $definition) {
                foreach ($this->columnsFor($field, $definition) as $column) {
                    $fields[$column] = $definition;
                }
            }

            foreach (Schema::getIndexes((new $model())->getTable()) as $index) {
                if (!$index['unique'] || $index['primary'] || count($index['columns']) !== 1) {
                    continue;
                }

                $column = $index['columns'][0];

                $this->assertArrayHasKey($column, $fields, "{$model}::{$column}");
                $this->assertTrue($fields[$column]->unique, "{$model}::{$column} is unique in the database but not declared unique");
            }
        }
    }

}
