<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class FileDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'name' => new Definition(ColumnType::Text),
                'path' => new Definition(ColumnType::Text),
                'size' => new Definition(ColumnType::Integer),
                'hash' => new Definition(ColumnType::Text),
                'description' => new Definition(ColumnType::Text),
                'mime_type' => new Definition(ColumnType::Text),
                'width' => new Definition(ColumnType::Integer),
                'height' => new Definition(ColumnType::Integer),
                'seconds' => new Definition(ColumnType::Integer),
                'privilege' => new Definition(ColumnType::Integer),
                'usage' => new Definition(ColumnType::Text)
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('file', 'name');
    }

}
