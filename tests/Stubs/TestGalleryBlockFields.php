<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;

class TestGalleryBlockFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return ['seconds' => Definition::integer()];
    }

}
