<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class CreatorUserAgent implements Generates {

    public function generate(mixed $value, Model $model): mixed {
        return Request::userAgent();
    }

}
