<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Synced;
use MatrixPlatform\Columns\Declarations\Variant;

class TestSyncedMenuFields implements Synced, Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'kind' => Definition::text(),
            'source' => Definition::integer()
        ];
    }

}
