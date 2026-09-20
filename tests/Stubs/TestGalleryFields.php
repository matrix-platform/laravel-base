<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Presentation;

class TestGalleryFields implements Variant {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'gallery' => Definition::json(Presentation::DriveImage, translatable: true)
        ];
    }

}
