<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Support\Facades\Request;

class CreatorEndpoint {

    public function generate() {
        return Request::path();
    }

}
