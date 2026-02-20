<?php //>

namespace MatrixPlatform\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Action {

    public function __construct(public $scope = null) {}

}
