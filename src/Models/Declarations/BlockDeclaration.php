<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class BlockDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'page_id' => Definition::integer(Presentation::Hidden),
                'type' => Definition::text(Presentation::Select),
                'title' => Definition::text(required: true),
                'data' => Definition::composite('block-data')
            ],
            Definitions::schedules(),
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('block', 'title', 'page', ranking: 'ranking', enable: 'enable_time', disable: 'disable_time');
    }

}
