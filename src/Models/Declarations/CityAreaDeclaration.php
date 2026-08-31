<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class CityAreaDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            ['city_id' => Definition::integer()],
            Definitions::title(),
            ['post_code' => Definition::text()],
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('area', 'title', 'city');
    }

}
