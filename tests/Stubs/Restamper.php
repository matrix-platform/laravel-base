<?php //>

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\Generators\Regenerates;

class Restamper implements Regenerates {

    public function regenerate(mixed $value, Model $model): mixed {
        return is_string($value) ? "{$value}." : 'stamped';
    }

}
