<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use MatrixPlatform\Columns\Column;

class Pool {

    /**
     * @param list<Column> $columns
     * @param array<string, string> $replaced
     */
    public function __construct(public readonly array $columns, public readonly array $replaced) {}

}
