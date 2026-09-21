<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;

class TestEditorFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return ['content' => Definition::text('editor', translatable: true)];
    }

}
