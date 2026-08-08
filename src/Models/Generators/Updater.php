<?php //>

namespace MatrixPlatform\Models\Generators;

use Illuminate\Database\Eloquent\Model;

class Updater implements Regenerates {

    public function regenerate(mixed $value, Model $model): mixed {
        return actor()->current()?->getKey();
    }

}
