<?php //>

namespace MatrixPlatform\Models\Declarations;

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
                'token' => Definition::text(),
                'type' => Definition::text(),
                'target_id' => Definition::integer(),
                'ip' => Definition::text(),
                'user_agent' => Definition::text(),
                'expire_time' => Definition::dateTime()
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('auth-token', 'token');
    }

}
