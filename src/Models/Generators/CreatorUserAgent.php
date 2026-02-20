<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Support\Facades\Request;

class CreatorUserAgent {

    public function generate() {
        return Request::userAgent();
    }

}
