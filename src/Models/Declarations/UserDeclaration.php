<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class UserDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'username' => new Definition(ColumnType::Text),
                'password' => new Definition(ColumnType::Text, Presentation::Password),
                'group_id' => new Definition(ColumnType::Integer)
            ],
            Definitions::permissions(),
            Definitions::schedules(),
            ['disabled' => new Definition(ColumnType::Boolean)],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('user', 'username');
    }

}
