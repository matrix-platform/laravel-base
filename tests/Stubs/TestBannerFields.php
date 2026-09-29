<?php //>

namespace Tests\Stubs;

use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Presets;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Presentation;

class TestBannerFields implements Presets, Variant {

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array {
        return ['layout' => 'wide'];
    }

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return [
            'layout' => Definition::text(Presentation::Select, options: new BundleOptions('block-layout')),
            'image' => Definition::json(Presentation::DriveImage)
        ];
    }

}
