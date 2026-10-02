<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Locked;
use MatrixPlatform\Columns\Declarations\Variant;

class TestLockedMenuFields implements Locked, Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return ['kind' => Definition::text()];
    }

}
