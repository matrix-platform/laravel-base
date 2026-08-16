<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class MemberLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'member_id' => new Definition(ColumnType::Integer),
                'type' => new Definition(ColumnType::Text),
                'content' => new Definition(ColumnType::Json),
                'ip' => new Definition(ColumnType::Text),
                'user_agent' => new Definition(ColumnType::Text)
            ],
            Definitions::auditings(false)
        );
    }

    public function metadata(): Metadata {
        return new Metadata('member-log', 'type');
    }

}
