<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;

class TestSocialFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'platform' => Definition::text(required: true),
            'url' => Definition::text(required: true)
        ];
    }

}
