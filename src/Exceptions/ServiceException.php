<?php //>

namespace MatrixPlatform\Exceptions;

use Exception;

class ServiceException extends Exception {

    private $error;

    public function __construct($error, $code) {
        $this->error = $error;

        parent::__construct(i18n("errors.{$error}"), $code);
    }

    public function getError() {
        return $this->error;
    }

}
