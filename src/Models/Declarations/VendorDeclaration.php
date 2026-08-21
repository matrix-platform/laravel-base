<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
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
                'username' => new Definition(ColumnType::Text),
                'password' => new Definition(ColumnType::Text, Presentation::Password, fn (): array => [
                    'exclude_if:password,null',
                    'regex:' . cfg('vendor.password-pattern')
                ]),
                'title' => new Definition(ColumnType::Text),
                'tax_id' => new Definition(ColumnType::Text),
                'contact' => new Definition(ColumnType::Text),
                'mobile' => new Definition(ColumnType::Text),
                'mail' => new Definition(ColumnType::Text),
                'status' => new Definition(ColumnType::Integer)
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('vendor', 'username');
    }

}
