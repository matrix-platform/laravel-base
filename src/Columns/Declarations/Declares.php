<?php //>

namespace MatrixPlatform\Columns\Declarations;

use MatrixPlatform\Support\Metadata;

interface Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array;

    public function metadata(): Metadata;

}
