<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
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
                'type' => new Definition(ColumnType::Integer),
                'endpoint' => new Definition(ColumnType::Text),
                'ip' => new Definition(ColumnType::Text),
                'data_type' => new Definition(ColumnType::Text),
                'data_id' => new Definition(ColumnType::Integer),
                'before' => new Definition(ColumnType::Json),
                'after' => new Definition(ColumnType::Json)
            ],
            Definitions::auditings(false)
        );
    }

    public function metadata(): Metadata {
        return new Metadata('manipulation-log', 'data_type');
    }

}
