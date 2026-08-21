<?php //>

namespace Tests\Support;

use RuntimeException;

class TestDatabaseGuard {

    public function __construct(private string $environment, private string $host, private string $database, private string $allowedHosts) {}

    public function databaseIdentifier(): string {
        $this->ensureSafe();

        return '"' . str_replace('"', '""', $this->database) . '"';
    }

    public function ensureSafe(): void {
        if ($this->environment !== 'testing') {
            throw new RuntimeException('Test database reset refused: APP_ENV must be testing');
        }

        if (preg_match('/\Alaravel_base_test(?:_[A-Za-z0-9_]+)?\z/', $this->database) !== 1) {
            throw new RuntimeException("Test database reset refused: database '{$this->database}' is not in the laravel_base_test namespace");
        }

        $hosts = preg_split('/[\s,;]+/', trim($this->allowedHosts), -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($hosts) || !in_array($this->host, $hosts, true)) {
            throw new RuntimeException("Test database reset refused: host '{$this->host}' is not allowed");
        }
    }

}
