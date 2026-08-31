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
                'username' => Definition::text(),
                'password' => Definition::text(Presentation::Password, fn (): array => ['exclude_if:password,null', 'regex:' . cfg('admin.password-pattern')]),
                'group_id' => Definition::integer()
            ],
            Definitions::permissions(),
            Definitions::schedules(),
            ['disabled' => Definition::boolean()],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('user', 'username');
    }

}
