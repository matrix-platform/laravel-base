<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class MemberDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'username' => Definition::text(),
                'password' => Definition::text(Presentation::Password, fn (): array => ['exclude_if:password,null', 'regex:' . cfg('member.password-pattern')]),
                'name' => Definition::text(),
                'mobile' => Definition::text(),
                'mail' => Definition::text(),
                'avatar' => Definition::text(),
                'status' => Definition::integer()
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('member', 'username');
    }

}
