<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class CreatorEndpoint implements Generates {

    public function generate(mixed $value, Model $model): mixed {
        return ConsoleRequest::current() || Request::ip() === null ? null : Request::path();
    }

}
