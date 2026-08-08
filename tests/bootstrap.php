<?php //>

require_once __DIR__ . '/../vendor/autoload.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: 'laravel_base_test';
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $username, $password);

$pdo->exec("DROP DATABASE IF EXISTS \"{$database}\"");
$pdo->exec("CREATE DATABASE \"{$database}\"");

register_shutdown_function(function () use ($pdo, $database): void {
    $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$database}' AND pid <> pg_backend_pid()");
    $pdo->exec("DROP DATABASE IF EXISTS \"{$database}\"");
});
