<?php //>

namespace MatrixPlatform\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Action {

    public function __construct(public ?string $path = null, public ?string $scope = null, public ?string $middleware = null, public bool $transaction = true) {}

}
