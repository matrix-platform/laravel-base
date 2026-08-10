<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Support\Metadata;

class StubDeclaration implements Declares {

    /**
     * @param array<string, Definition> $definitions
     */
    public function __construct(private Metadata $metadata, private array $definitions = []) {}

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return $this->definitions;
    }

    public function metadata(): Metadata {
        return $this->metadata;
    }

}
