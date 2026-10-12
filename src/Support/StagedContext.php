<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;

class StagedContext {

    public ?Model $model = null;

    /**
     * @var array<string, mixed>
     */
    public array $parent = [];

}
