<?php //>

namespace MatrixPlatform\Exceptions;

use Exception;

class ServiceException extends Exception {

    public function __construct(private $error, $code) {
        parent::__construct(i18n("errors.{$error}"), $code);
    }

    public function getError() {
        return $this->error;
    }

}
