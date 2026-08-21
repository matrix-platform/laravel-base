<?php //>

namespace Tests\Unit\Support;

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

    public function test_a_database_outside_the_test_namespace_is_refused(): void {
        $guard = new TestDatabaseGuard('testing', '127.0.0.1', 'production', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("database 'production' is not in the laravel_base_test namespace");

        $guard->ensureSafe();
    }

    public function test_a_host_outside_the_explicit_allowlist_is_refused(): void {
        $guard = new TestDatabaseGuard('testing', 'database.internal', 'laravel_base_test', '127.0.0.1,postgres');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("host 'database.internal' is not allowed");

        $guard->ensureSafe();
    }

    public function test_the_safe_database_identifier_is_quoted_for_postgresql(): void {
        $guard = new TestDatabaseGuard('testing', 'postgres', 'laravel_base_test_ci_2', '127.0.0.1 postgres');

        $this->assertSame('"laravel_base_test_ci_2"', $guard->databaseIdentifier());
    }

}
