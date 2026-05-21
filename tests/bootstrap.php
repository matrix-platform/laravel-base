<?php //>

require_once __DIR__ . '/../vendor/autoload.php';

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '5432';
$dbName   = getenv('DB_DATABASE') ?: 'laravel_base_test';
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=postgres", $username, $password);
$pdo->exec("DROP DATABASE IF EXISTS \"$dbName\"");
$pdo->exec("CREATE DATABASE \"$dbName\"");

register_shutdown_function(static function () use ($pdo, $dbName) {
    $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$dbName' AND pid <> pg_backend_pid()");
    $pdo->exec("DROP DATABASE IF EXISTS \"$dbName\"");
});
