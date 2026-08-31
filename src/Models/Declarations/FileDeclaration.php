<?php //>

namespace MatrixPlatform\Models\Declarations;

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
                'name' => Definition::text(),
                'path' => Definition::text(),
                'size' => Definition::integer(),
                'hash' => Definition::text(),
                'description' => Definition::text(),
                'mime_type' => Definition::text(),
                'width' => Definition::integer(),
                'height' => Definition::integer(),
                'seconds' => Definition::integer(),
                'privilege' => Definition::integer(),
                'usage' => Definition::text()
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('file', 'name');
    }

}
