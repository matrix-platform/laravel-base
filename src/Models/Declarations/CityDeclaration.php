<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class CityDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            ['title' => new Definition(ColumnType::Text)],
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('city');
    }

}
