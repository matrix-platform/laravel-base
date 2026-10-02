<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Leaf;
use MatrixPlatform\Columns\Declarations\Variant;

class TestLeafMenuFields implements Leaf, Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return ['kind' => Definition::text()];
    }

}
