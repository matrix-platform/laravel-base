<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Presets;
use MatrixPlatform\Columns\Declarations\Variant;

class TestPresetPageFields implements Presets, Variant {

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array {
        return ['layout' => 'full'];
    }

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'layout' => Definition::text()
        ];
    }

}
