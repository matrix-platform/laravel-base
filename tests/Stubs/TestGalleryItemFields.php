<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Presentation;

class TestGalleryItemFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'caption' => Definition::text(translatable: true),
            'image' => Definition::json(Presentation::DriveImage)
        ];
    }

}
