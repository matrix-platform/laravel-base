<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class OperatorDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'type' => new Definition(ColumnType::Text),
                'username' => new Definition(ColumnType::Text)
            ]
        );
    }

    public function metadata(): Metadata {
        return new Metadata('operator', 'username');
    }

}
