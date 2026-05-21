<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Support\Facades\Request;

class CreatorAddress {

    public function generate() {
        return Request::ip();
    }

}
