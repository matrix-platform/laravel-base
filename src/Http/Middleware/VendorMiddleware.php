<?php //>

namespace MatrixPlatform\Http\Middleware;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Vendor;

/**
 * @extends IdentityMiddleware<Vendor>
 */
class VendorMiddleware extends IdentityMiddleware {

    protected function assign(Model $subject): void {
        actor()->setVendor($subject);
    }

    protected function subject(int $id): ?Vendor {
        $class = $this->configured('vendor-model', Vendor::class);

        return $class::query()
            ->whereKey($id)
            ->whereEnabled()
            ->first();
    }

    protected function type(): IdentityType {
        return IdentityType::Vendor;
    }

}
