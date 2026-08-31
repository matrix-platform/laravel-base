<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class ManipulationLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'type' => Definition::integer(),
                'endpoint' => Definition::text(),
                'ip' => Definition::text(),
                'data_type' => Definition::text(),
                'data_id' => Definition::integer(),
                'before' => Definition::json(),
                'after' => Definition::json()
            ],
            Definitions::auditings(false)
        );
    }

    public function metadata(): Metadata {
        return new Metadata('manipulation-log', 'data_type');
    }

}
