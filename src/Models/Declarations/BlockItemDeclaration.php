<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class BlockItemDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'block_id' => Definition::integer(Presentation::Hidden),
                'title' => Definition::text(required: true),
                'data' => Definition::composite('block-item-data')
            ],
            Definitions::schedules(),
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('item', 'title', 'block', ranking: 'ranking', enable: 'enable_time', disable: 'disable_time');
    }

}
