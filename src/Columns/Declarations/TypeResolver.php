<?php //>

namespace MatrixPlatform\Columns\Declarations;

use Illuminate\Database\Eloquent\Model;

interface TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string;

}
