<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Database\Eloquent\Model;

interface Regenerates {

    public function regenerate(mixed $value, Model $model): mixed;

}
