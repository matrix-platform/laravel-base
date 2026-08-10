<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Support\Metadata;

class AuthTokenDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'token' => new Definition(ColumnType::Text),
                'type' => new Definition(ColumnType::Text),
                'target_id' => new Definition(ColumnType::Integer),
                'ip' => new Definition(ColumnType::Text),
                'user_agent' => new Definition(ColumnType::Text),
                'expire_time' => new Definition(ColumnType::DateTime)
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('auth-token', 'token');
    }

}
