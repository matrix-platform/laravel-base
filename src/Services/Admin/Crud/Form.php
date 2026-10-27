<?php //>

namespace MatrixPlatform\Services\Admin\Crud;

use MatrixPlatform\Columns\Column;

class Form {

    /**
     * @var array<string, true>
     */
    private array $subnames = [];

    /**
     * @param list<Column> $columns
     * @param array<string, array<string, Column>> $composites
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(public readonly array $columns, public readonly array $composites = [], public readonly array $presets = []) {
        foreach ($composites as $subfields) {
            foreach ($subfields as $subfield) {
                $this->subnames[$subfield->name] = true;
            }
        }
    }

    public function composed(Column $column): bool {
        return array_key_exists($column->name, $this->subnames);
    }

}
