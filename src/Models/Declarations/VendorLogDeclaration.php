<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class VendorLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'vendor_id' => Definition::integer(),
                'type' => Definition::text(),
                'content' => Definition::json(),
                'ip' => Definition::text(),
                'user_agent' => Definition::text()
            ],
            Definitions::auditings(false)
        );
    }

    public function metadata(): Metadata {
        return new Metadata('vendor-log', 'type');
    }

}
