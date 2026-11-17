<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;

class TestNestedCompositeFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return ['inner' => Definition::composite('block-data')];
    }

}
