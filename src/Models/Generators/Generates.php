<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Database\Eloquent\Model;

interface Generates {

    public function generate(mixed $value, Model $model): mixed;

}
