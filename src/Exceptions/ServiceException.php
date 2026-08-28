<?php //>

namespace MatrixPlatform\Exceptions;

use Exception;

class ServiceException extends Exception {

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(private string $error, int $code = 500, private array $extra = []) {
        parent::__construct($error, $code);
    }

    public function getError(): string {
        return $this->error;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array {
        return $this->extra;
    }

    public function report(): bool {
        return false;
    }

}
