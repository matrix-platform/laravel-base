<?php //>

namespace MatrixPlatform\Columns\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

class Raw implements Expression {

    public function __construct(private string $sql) {}

    public function getValue(Grammar $grammar): string {
        return $this->sql;
    }

}
