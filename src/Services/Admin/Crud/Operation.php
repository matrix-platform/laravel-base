<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use Closure;

class Operation {

    public function __construct(public readonly string $type, public readonly ?Closure $when = null) {}

}
