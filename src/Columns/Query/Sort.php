<?php //>

namespace MatrixPlatform\Columns\Query;

class Sort {

    public function __construct(public readonly string $name, public readonly Direction $direction) {}

}
