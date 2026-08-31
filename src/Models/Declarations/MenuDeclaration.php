<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class MenuDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            ['parent_id' => Definition::integer()],
            Definitions::title(),
            ['data' => Definition::json()],
            Definitions::schedules(),
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('menu');
    }

}
