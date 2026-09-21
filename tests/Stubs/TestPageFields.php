<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;

class TestPageFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'layout' => Definition::text(),
            'lead' => Definition::text(translatable: true)
        ];
    }

}
