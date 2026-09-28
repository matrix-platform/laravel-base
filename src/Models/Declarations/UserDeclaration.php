<?php //>

namespace MatrixPlatform\Models\Declarations;

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
                'username' => Definition::text(required: true, unique: true),
                'name' => Definition::text(),
                'mail' => Definition::text(rule: ['email']),
                'phone' => Definition::text(),
                'password' => Definition::text(Presentation::Password, fn (): array => ['exclude_if:password,null', 'password_format:admin']),
                'group_id' => Definition::integer()
            ],
            Definitions::disabled(),
            [
                'enable_time' => Definition::dateTime(),
                'disable_time' => Definition::dateTime()
            ],
            [
                'secret' => Definition::text(Presentation::Hidden),
                'confirmed_time' => Definition::dateTime()
            ],
            Definitions::permissions(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('user', 'username');
    }

}
