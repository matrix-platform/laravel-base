<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class VendorDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'username' => Definition::text(),
                'password' => Definition::text(Presentation::Password, fn (): array => ['exclude_if:password,null', 'regex:' . cfg('vendor.password-pattern')]),
                'title' => Definition::text(),
                'tax_id' => Definition::text(),
                'contact' => Definition::text(),
                'mobile' => Definition::text(),
                'mail' => Definition::text(),
                'status' => Definition::integer()
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('vendor', 'username');
    }

}
