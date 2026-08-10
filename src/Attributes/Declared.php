<?php //>

namespace MatrixPlatform\Attributes;

use Attribute;
use MatrixPlatform\Columns\Declarations\Declares;

#[Attribute(Attribute::TARGET_CLASS)]
class Declared {

    /**
     * @param class-string<Declares> $declaration
     */
    public function __construct(public string $declaration) {}

}
