<?php //>

namespace MatrixPlatform\Exceptions;

use Exception;

class ServiceException extends Exception {

    public function __construct(private string $error, int $code = 500) {
        parent::__construct($error, $code);
    }

    public function getError(): string {
        return $this->error;
    }

}
