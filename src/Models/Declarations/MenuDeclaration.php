<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
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
            [
                'parent_id' => new Definition(ColumnType::Integer),
                'title' => new Definition(ColumnType::Text),
                'data' => new Definition(ColumnType::Json)
            ],
            Definitions::schedules(),
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('menu');
    }

}
