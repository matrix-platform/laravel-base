<?php //>

namespace MatrixPlatform\Models\Builders;

use MatrixPlatform\Models\Vendor;

/**
 * @extends BaseBuilder<Vendor>
 */
class VendorBuilder extends BaseBuilder {

    public function whereEnabled(): static {
        return $this->where('status', Vendor::ENABLED);
    }

}
