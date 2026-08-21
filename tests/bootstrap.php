<?php //>

require_once __DIR__ . '/../vendor/autoload.php';

$environment = getenv('APP_ENV') ?: '';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: 'laravel_base_test';
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';
$allowedHosts = getenv('TEST_DATABASE_ALLOWED_HOSTS') ?: '127.0.0.1';
$guard = new Tests\Support\TestDatabaseGuard($environment, $host, $database, $allowedHosts);
$databaseIdentifier = $guard->databaseIdentifier();

$pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $username, $password);
$databaseLiteral = $pdo->quote($database);

if ($databaseLiteral === false) {
    throw new RuntimeException('Test database reset refused: the database name could not be quoted');
}

$pdo->exec("DROP DATABASE IF EXISTS {$databaseIdentifier}");
$pdo->exec("CREATE DATABASE {$databaseIdentifier}");

register_shutdown_function(function () use ($pdo, $databaseIdentifier, $databaseLiteral): void {
    $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = {$databaseLiteral} AND pid <> pg_backend_pid()");
    $pdo->exec("DROP DATABASE IF EXISTS {$databaseIdentifier}");
});
