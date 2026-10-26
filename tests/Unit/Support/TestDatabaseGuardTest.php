<?php //>

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase {

    public function test_testing_environment_with_the_default_database_and_host_is_safe(): void {
        $guard = new TestDatabaseGuard('testing', '127.0.0.1', 'laravel_base_test', '127.0.0.1');

        $guard->ensureSafe();

        $this->addToAssertionCount(1);
    }

    public function test_a_non_testing_environment_is_refused(): void {
        $guard = new TestDatabaseGuard('production', '127.0.0.1', 'laravel_base_test', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV must be testing');

        $guard->ensureSafe();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignDatabases(): array {
        return [
            'an unrelated name' => ['production'],
            'a look-alike prefix' => ['laravel_base_testing'],
            'a trailing separator only' => ['laravel_base_test_'],
            'a suffix with punctuation' => ['laravel_base_test_x;drop']
        ];
    }

    #[DataProvider('foreignDatabases')]
    public function test_a_database_outside_the_test_namespace_is_refused(string $database): void {
        $guard = new TestDatabaseGuard('testing', '127.0.0.1', $database, '127.0.0.1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("database '{$database}' is not in the laravel_base_test namespace");

        $guard->ensureSafe();
    }

    public function test_a_host_outside_the_explicit_allowlist_is_refused(): void {
        $guard = new TestDatabaseGuard('testing', 'database.internal', 'laravel_base_test', '127.0.0.1,postgres');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("host 'database.internal' is not allowed");

        $guard->ensureSafe();
    }

    public function test_an_unsafe_guard_refuses_to_hand_out_a_database_identifier(): void {
        $guard = new TestDatabaseGuard('production', '127.0.0.1', 'laravel_base_test', '127.0.0.1');

        $this->expectException(RuntimeException::class);

        $guard->databaseIdentifier();
    }

    public function test_the_safe_database_identifier_is_quoted_for_postgresql(): void {
        $guard = new TestDatabaseGuard('testing', 'postgres', 'laravel_base_test_ci_2', '127.0.0.1 postgres');

        $this->assertSame('"laravel_base_test_ci_2"', $guard->databaseIdentifier());
    }

}
